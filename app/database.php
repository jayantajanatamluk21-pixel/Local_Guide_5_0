<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dir = APP_ROOT . '/storage';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Storage folder is not writable.');
    $path = getenv('DATABASE_PATH') ?: 'storage/metroguide.sqlite';
    if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:\\\\/', $path)) $path = APP_ROOT . '/' . $path;
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('PRAGMA journal_mode=WAL');
    initialize_database($pdo);
    return $pdo;
}

function ensure_column(PDO $p, string $table, string $column, string $definition): void {
    $columns = $p->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $c) if ($c['name'] === $column) return;
    $p->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function initialize_database(PDO $p): void {
    $p->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        username TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'admin',
        phone TEXT NOT NULL DEFAULT '',
        preferred_language TEXT NOT NULL DEFAULT 'en',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS listings (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL, location TEXT NOT NULL, address TEXT NOT NULL DEFAULT '',
        phone TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', website TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL, tags TEXT NOT NULL DEFAULT '', image_path TEXT NOT NULL DEFAULT '',
        image_url TEXT NOT NULL DEFAULT '', meta_title TEXT NOT NULL DEFAULT '', meta_description TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('published','draft','pending')),
        featured INTEGER NOT NULL DEFAULT 0, is_demo INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, reviewer_name TEXT NOT NULL,
        rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5), comment TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '')");

    // Safe additive migrations: existing installations keep their listings and administrator account.
    $listingColumns = [
        'city' => "TEXT NOT NULL DEFAULT ''", 'latitude' => 'REAL', 'longitude' => 'REAL',
        'whatsapp' => "TEXT NOT NULL DEFAULT ''", 'price_range' => "TEXT NOT NULL DEFAULT ''",
        'opening_hours' => "TEXT NOT NULL DEFAULT ''", 'owner_user_id' => 'INTEGER',
        'verified' => 'INTEGER NOT NULL DEFAULT 0', 'sponsored' => 'INTEGER NOT NULL DEFAULT 0'
    ];
    foreach ($listingColumns as $column=>$definition) ensure_column($p, 'listings', $column, $definition);
    foreach (['username'=>"TEXT NOT NULL DEFAULT ''", 'phone'=>"TEXT NOT NULL DEFAULT ''", 'preferred_language'=>"TEXT NOT NULL DEFAULT 'en'", 'is_active'=>'INTEGER NOT NULL DEFAULT 1', 'bio'=>"TEXT NOT NULL DEFAULT ''"] as $column=>$definition) ensure_column($p, 'users', $column, $definition);
    foreach (['two_factor_secret_enc'=>"TEXT NOT NULL DEFAULT ''", 'two_factor_enabled'=>'INTEGER NOT NULL DEFAULT 0'] as $column=>$definition) ensure_column($p, 'users', $column, $definition);
    // Backfill login handles for pre-3.1 accounts without changing their existing email or password.
    $legacyUsers = $p->query("SELECT id,email FROM users WHERE username='' OR username IS NULL ORDER BY id ASC")->fetchAll();
    foreach ($legacyUsers as $legacyUser) {
        $emailLocal = explode('@', (string)$legacyUser['email'])[0] ?? '';
        $base = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9._-]+/', '-', $emailLocal), '._-'));
        if (strlen($base) < 3 || !preg_match('/^[a-z0-9]/', $base)) $base = 'member';
        $candidate = substr($base, 0, 24);
        $suffix = 1;
        while (true) {
            $check = $p->prepare('SELECT 1 FROM users WHERE username=:u COLLATE NOCASE AND id<>:id LIMIT 1');
            $check->execute([':u'=>$candidate, ':id'=>(int)$legacyUser['id']]);
            if (!$check->fetchColumn()) break;
            $tail = '-' . (int)$legacyUser['id'] . ($suffix > 1 ? '-' . $suffix : '');
            $candidate = substr($base, 0, max(3, 30 - strlen($tail))) . $tail;
            $suffix++;
        }
        $p->prepare('UPDATE users SET username=:u WHERE id=:id')->execute([':u'=>$candidate, ':id'=>(int)$legacyUser['id']]);
    }
    $p->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username_nocase ON users(username COLLATE NOCASE)');
    ensure_column($p, 'reviews', 'user_id', 'INTEGER');

    $p->exec("CREATE TABLE IF NOT EXISTS favorites (
        user_id INTEGER NOT NULL, listing_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(user_id,listing_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS owner_claims (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
        business_email TEXT NOT NULL DEFAULT '', business_phone TEXT NOT NULL DEFAULT '', message TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
        reviewed_by INTEGER, reviewed_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS listing_views (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, visitor_hash TEXT NOT NULL,
        viewed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS search_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, query_text TEXT NOT NULL DEFAULT '', area_text TEXT NOT NULL DEFAULT '',
        result_count INTEGER NOT NULL DEFAULT 0, visitor_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
        description TEXT NOT NULL, venue TEXT NOT NULL DEFAULT '', location TEXT NOT NULL DEFAULT '',
        starts_at TEXT NOT NULL, ends_at TEXT NOT NULL DEFAULT '', image_url TEXT NOT NULL DEFAULT '',
        organizer_url TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
        created_by INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS offers (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, title TEXT NOT NULL,
        description TEXT NOT NULL, promo_code TEXT NOT NULL DEFAULT '', starts_at TEXT NOT NULL DEFAULT '',
        ends_at TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','expired','rejected')),
        created_by INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS trip_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL DEFAULT 'My local day out',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS trip_plan_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT, plan_id INTEGER NOT NULL, listing_id INTEGER NOT NULL,
        position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(plan_id,listing_id), FOREIGN KEY(plan_id) REFERENCES trip_plans(id) ON DELETE CASCADE,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS enquiries (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, user_id INTEGER,
        sender_name TEXT NOT NULL, sender_email TEXT NOT NULL, sender_phone TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','read','closed')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    foreach ([
        'pipeline_stage'=>"TEXT NOT NULL DEFAULT 'new'", 'owner_notes'=>"TEXT NOT NULL DEFAULT ''",
        'follow_up_at'=>"TEXT NOT NULL DEFAULT ''", 'updated_at'=>"TEXT NOT NULL DEFAULT ''"
    ] as $column=>$definition) ensure_column($p, 'enquiries', $column, $definition);

    // Version 3.0: marketplace, community, contributor and intelligence modules.
    $p->exec("CREATE TABLE IF NOT EXISTS availability_slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, slot_date TEXT NOT NULL,
        slot_time TEXT NOT NULL, capacity INTEGER NOT NULL DEFAULT 1, booked_count INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(listing_id,slot_date,slot_time), FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, slot_id INTEGER,
        user_id INTEGER, guest_name TEXT NOT NULL, guest_email TEXT NOT NULL, guest_phone TEXT NOT NULL DEFAULT '',
        booking_date TEXT NOT NULL, booking_time TEXT NOT NULL, party_size INTEGER NOT NULL DEFAULT 1,
        notes TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'requested' CHECK(status IN ('requested','confirmed','declined','cancelled','completed')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(slot_id) REFERENCES availability_slots(id) ON DELETE SET NULL,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS quote_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, user_id INTEGER,
        sender_name TEXT NOT NULL, sender_email TEXT NOT NULL, sender_phone TEXT NOT NULL DEFAULT '',
        request_text TEXT NOT NULL, budget TEXT NOT NULL DEFAULT '', needed_by TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','in_progress','quoted','won','lost','closed')),
        quoted_amount TEXT NOT NULL DEFAULT '', owner_notes TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, customer_id INTEGER NOT NULL,
        owner_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(listing_id,customer_id),
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(customer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER NOT NULL, sender_id INTEGER NOT NULL,
        body TEXT NOT NULL, read_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS price_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '', price REAL, price_unit TEXT NOT NULL DEFAULT 'per service',
        is_active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS listing_photos (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, user_id INTEGER,
        image_path TEXT NOT NULL, caption TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS guides (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE, description TEXT NOT NULL, city TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS guide_items (
        guide_id INTEGER NOT NULL, listing_id INTEGER NOT NULL, position INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY(guide_id,listing_id), FOREIGN KEY(guide_id) REFERENCES guides(id) ON DELETE CASCADE,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS contributor_follows (
        follower_id INTEGER NOT NULL, contributor_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(follower_id,contributor_id), CHECK(follower_id<>contributor_id),
        FOREIGN KEY(follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(contributor_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS review_votes (
        review_id INTEGER NOT NULL, user_id INTEGER NOT NULL, helpful INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(review_id,user_id),
        FOREIGN KEY(review_id) REFERENCES reviews(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
        title TEXT NOT NULL, body TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT, question_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
        body TEXT NOT NULL, is_owner_answer INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS recommendations (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
        note TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS recommendation_votes (
        recommendation_id INTEGER NOT NULL, user_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(recommendation_id,user_id),
        FOREIGN KEY(recommendation_id) REFERENCES recommendations(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS content_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT, reporter_id INTEGER, content_type TEXT NOT NULL,
        content_id INTEGER NOT NULL, reason TEXT NOT NULL, details TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','reviewed','dismissed')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS offer_redemptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, offer_id INTEGER NOT NULL, user_id INTEGER,
        visitor_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(offer_id,visitor_hash), FOREIGN KEY(offer_id) REFERENCES offers(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Version 4.0: personal collections, community social feed, notifications and API foundations.
    $p->exec("CREATE TABLE IF NOT EXISTS user_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
        title TEXT NOT NULL, body TEXT NOT NULL DEFAULT '', href TEXT NOT NULL DEFAULT '',
        read_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS saved_collections (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
        title TEXT NOT NULL, description TEXT NOT NULL DEFAULT '',
        visibility TEXT NOT NULL DEFAULT 'private' CHECK(visibility IN ('private','unlisted','public')),
        share_token TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS saved_collection_items (
        collection_id INTEGER NOT NULL, listing_id INTEGER NOT NULL,
        position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(collection_id,listing_id),
        FOREIGN KEY(collection_id) REFERENCES saved_collections(id) ON DELETE CASCADE,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS community_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
        listing_id INTEGER, body TEXT NOT NULL, image_path TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS community_post_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
        body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'published' CHECK(status IN ('published','hidden')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS community_post_reactions (
        post_id INTEGER NOT NULL, user_id INTEGER NOT NULL, reaction TEXT NOT NULL DEFAULT 'like',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(post_id,user_id),
        FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS user_badges (
        user_id INTEGER NOT NULL, badge_key TEXT NOT NULL, awarded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(user_id,badge_key),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS saved_searches (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL,
        query_text TEXT NOT NULL DEFAULT '', area_text TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS api_request_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, route TEXT NOT NULL, visitor_hash TEXT NOT NULL,
        requested_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    foreach ([
        'avatar_path'=>"TEXT NOT NULL DEFAULT ''", 'home_city'=>"TEXT NOT NULL DEFAULT ''",
        'interests'=>"TEXT NOT NULL DEFAULT ''", 'profile_visibility'=>"TEXT NOT NULL DEFAULT 'public'"
    ] as $column=>$definition) ensure_column($p, 'users', $column, $definition);

    $p->exec('CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON user_notifications(user_id,read_at,created_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_collections_user_updated ON saved_collections(user_id,updated_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_posts_state_date ON community_posts(status,created_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_post_comments ON community_post_comments(post_id,status,created_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_api_route_date ON api_request_logs(route,requested_at)');

    // Version 5.0: operational audit trail, owner CRM tasks, monetization ledger,
    // integration registry and feature controls. All migrations are additive.
    $p->exec("CREATE TABLE IF NOT EXISTS security_audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER, actor_role TEXT NOT NULL DEFAULT 'guest',
        event_key TEXT NOT NULL, severity TEXT NOT NULL DEFAULT 'info' CHECK(severity IN ('info','warning','critical')),
        context_json TEXT NOT NULL DEFAULT '{}', visitor_fingerprint TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS business_tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, owner_user_id INTEGER NOT NULL,
        enquiry_id INTEGER, title TEXT NOT NULL, notes TEXT NOT NULL DEFAULT '',
        due_at TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'todo' CHECK(status IN ('todo','in_progress','done')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(enquiry_id) REFERENCES enquiries(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS membership_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT, plan_key TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
        price_minor INTEGER NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'INR', interval_key TEXT NOT NULL DEFAULT 'month',
        features_json TEXT NOT NULL DEFAULT '[]', is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS business_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, listing_id INTEGER NOT NULL, owner_user_id INTEGER NOT NULL,
        plan_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','trial','active','past_due','cancelled','expired')),
        provider TEXT NOT NULL DEFAULT 'manual', provider_subscription_id TEXT NOT NULL DEFAULT '',
        current_period_start TEXT NOT NULL DEFAULT '', current_period_end TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE,
        FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(plan_id) REFERENCES membership_plans(id)
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS payment_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, subscription_id INTEGER, listing_id INTEGER,
        provider TEXT NOT NULL DEFAULT 'manual', provider_reference TEXT NOT NULL DEFAULT '',
        amount_minor INTEGER NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'INR',
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','succeeded','failed','refunded')),
        metadata_json TEXT NOT NULL DEFAULT '{}', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(provider,provider_reference),
        FOREIGN KEY(subscription_id) REFERENCES business_subscriptions(id) ON DELETE SET NULL,
        FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE SET NULL
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS integration_registry (
        integration_key TEXT PRIMARY KEY, provider TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 0,
        notes TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS feature_flags (
        flag_key TEXT PRIMARY KEY, enabled INTEGER NOT NULL DEFAULT 1, description TEXT NOT NULL DEFAULT '',
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $p->exec("CREATE TABLE IF NOT EXISTS admin_announcements (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT NOT NULL,
        severity TEXT NOT NULL DEFAULT 'info' CHECK(severity IN ('info','warning','critical')),
        starts_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, ends_at TEXT NOT NULL DEFAULT '',
        is_active INTEGER NOT NULL DEFAULT 1, created_by INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    $p->exec('CREATE INDEX IF NOT EXISTS idx_audit_date ON security_audit_logs(created_at,severity)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_tasks_owner_due ON business_tasks(owner_user_id,status,due_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_subscriptions_status ON business_subscriptions(status,updated_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_payments_status ON payment_transactions(status,created_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_announcements_live ON admin_announcements(is_active,starts_at,ends_at)');
    $planDefaults = [
        ['free','Free',0,'INR','month',['1 listing','Basic profile','Standard discovery']],
        ['growth','Growth',49900,'INR','month',['Enhanced profile','Offers and promotions','Business insights']],
        ['pro','Pro',149900,'INR','month',['Priority placement eligibility','Advanced analytics','CRM workflow tools']]
    ];
    $planInsert=$p->prepare('INSERT OR IGNORE INTO membership_plans(plan_key,name,price_minor,currency,interval_key,features_json) VALUES(:k,:n,:p,:c,:i,:f)');
    foreach($planDefaults as [$k,$n,$price,$currency,$interval,$features]) $planInsert->execute([':k'=>$k,':n'=>$n,':p'=>$price,':c'=>$currency,':i'=>$interval,':f'=>json_encode($features,JSON_UNESCAPED_UNICODE)]);
    $featureDefaults = [
        ['community_feed',1,'Community feed and post interactions'],
        ['business_crm_tasks',1,'Owner business task pipeline'],
        ['business_subscriptions',1,'Plan and subscription recordkeeping'],
        ['smart_discovery',1,'Rule-based local discovery'],
        ['offline_shell',1,'Static PWA offline shell']
    ];
    $flagInsert=$p->prepare('INSERT OR IGNORE INTO feature_flags(flag_key,enabled,description) VALUES(:k,:e,:d)');
    foreach($featureDefaults as [$k,$enabled,$description]) $flagInsert->execute([':k'=>$k,':e'=>$enabled,':d'=>$description]);

    $p->exec('CREATE INDEX IF NOT EXISTS idx_listing_state_cat ON listings(status,category)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_listing_geo ON listings(latitude,longitude)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_review_listing_state ON reviews(listing_id,status)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_views_date ON listing_views(viewed_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_events_dates ON events(status,starts_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_offers_dates ON offers(status,ends_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_bookings_listing_status ON bookings(listing_id,status,booking_date)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_quotes_listing_status ON quote_requests(listing_id,status,created_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id,created_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_photos_listing_status ON listing_photos(listing_id,status)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_guides_status ON guides(status,created_at)');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_questions_status ON questions(status,created_at)');

    // 5.1/6.0 Additive upgrades: QR Voucher redemption, points, and rich listings
    ensure_column($p, 'offer_redemptions', 'voucher_code', "TEXT NOT NULL DEFAULT ''");
    ensure_column($p, 'offer_redemptions', 'status', "TEXT NOT NULL DEFAULT 'active'");
    ensure_column($p, 'offer_redemptions', 'redeemed_at', "TEXT");
    ensure_column($p, 'offer_redemptions', 'redeemed_by', "INTEGER");
    ensure_column($p, 'users', 'contributor_points', "INTEGER NOT NULL DEFAULT 0");
    ensure_column($p, 'listings', 'instagram_url', "TEXT NOT NULL DEFAULT ''");
    ensure_column($p, 'listings', 'menu_url', "TEXT NOT NULL DEFAULT ''");
    $p->exec('CREATE INDEX IF NOT EXISTS idx_offer_vouchers ON offer_redemptions(voucher_code)');

    $defaults = [
        'site_name'=>'MetroGuide','site_city'=>'Your City','site_tagline'=>'Find the good things around you.',
        'site_description'=>'Discover independent businesses, essential services, neighbourhood favourites and local experiences.',
        'hero_title'=>'Your city, better explored.','hero_subtitle'=>'Find trusted local businesses, hidden gems and everyday essentials — all in one thoughtfully curated guide.',
        'contact_email'=>'','contact_phone'=>'','footer_note'=>'Made for the people and places that make a neighbourhood.',
        'default_language'=>'en','currency'=>'INR','platform_version'=>'5.0.0'
    ];
    $ins=$p->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(:k,:v)');
    foreach($defaults as $k=>$v) $ins->execute([':k'=>$k,':v'=>$v]);
    $p->prepare("INSERT INTO settings(key,value) VALUES('platform_version','5.0.0') ON CONFLICT(key) DO UPDATE SET value=excluded.value WHERE settings.value<>excluded.value")->execute();
    $p->exec("UPDATE settings SET value='LocalGuide' WHERE key='site_name' AND value='MetroGuide'");
    $p->exec("UPDATE settings SET value='Discover more. Experience better.' WHERE key='site_tagline' AND value='Find the good things around you.'");

    $seed=filter_var(getenv('SEED_DEMO')===false?'true':getenv('SEED_DEMO'),FILTER_VALIDATE_BOOLEAN);
    if((int)$p->query('SELECT COUNT(*) FROM listings')->fetchColumn()===0 && $seed) seed_demo_listings($p);
}

function seed_demo_listings(PDO $p): void {
    $rows = [
        ['Sample Coffee House','Food & Cafes','Riverside Quarter','A sample neighbourhood café with speciality coffee, fresh bakes and relaxed seating. Replace this with verified business details.','https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=1200&q=85','Coffee, Bakery, Wi-Fi',1],
        ['Sample Heritage Gallery','Heritage & Spots','Old Town','A sample cultural listing for galleries, historic places and community-led experiences. Add accurate visitor details.','https://images.unsplash.com/photo-1577083552431-6e5fd01aa342?auto=format&fit=crop&w=1200&q=85','Art, Culture, History',1],
        ['Sample Work & Create Studio','Tech Hubs','Innovation District','A sample workspace listing for coworking, workshops and creative teams. Update with real amenities and contact details.','https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=85','Coworking, Events, Workspace',0],
        ['Sample Home Services','Home Services','North Neighbourhood','A sample service listing. Add verified service areas, phone details and real service information.','https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&w=1200&q=85','Repairs, Maintenance, Local',0],
        ['Sample Wellness Studio','Health & Wellness','Garden Road','A sample wellness directory entry. Replace with accurate practitioner credentials and service details.','https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1200&q=85','Wellness, Fitness, Classes',0],
        ['Sample Neighbourhood Market','Shopping','Market Lane','A sample independent shopping listing for local makers, everyday essentials and neighbourhood retail.','https://images.unsplash.com/photo-1488459716781-31db52582fe9?auto=format&fit=crop&w=1200&q=85','Shopping, Independent, Local',0],
    ];
    $s=$p->prepare("INSERT INTO listings(name,slug,category,location,city,description,image_url,tags,status,featured,is_demo) VALUES(:n,:slug,:c,:l,:city,:d,:img,:tags,'published',:f,1)");
    foreach($rows as $r){[$n,$c,$l,$d,$img,$tags,$f]=$r;$s->execute([':n'=>$n,':slug'=>slugify($n),':c'=>$c,':l'=>$l,':city'=>$l,':d'=>$d,':img'=>$img,':tags'=>$tags,':f'=>$f]);}
}
