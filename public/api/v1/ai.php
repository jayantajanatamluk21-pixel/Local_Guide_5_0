<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
api_rate_limit('v1-ai', 60, 60);

header('Content-Type: application/json; charset=utf-8');

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

try {
    if ($action === 'review-summary') {
        $listingId = (int)($_GET['listing_id'] ?? $_POST['listing_id'] ?? 0);
        if ($listingId <= 0) throw new InvalidArgumentException('Invalid listing ID.');

        $result = ai_summarize_listing_reviews($listingId);
        json_response([
            'status' => 'ok',
            'data' => $result
        ]);
    }

    if ($action === 'generate-copy') {
        if (!is_owner()) throw new RuntimeException('Only business owners can use the AI copy generator.');
        $business = trim((string)($_POST['name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        $format = trim((string)($_POST['format'] ?? 'seo_desc'));
        $tone = trim((string)($_POST['tone'] ?? 'friendly'));

        if ($business === '') throw new InvalidArgumentException('Business name is required.');

        $result = ai_generate_business_copy($business, $category, $location, $details, $format, $tone);
        json_response([
            'status' => 'ok',
            'data' => $result
        ]);
    }

    if ($action === 'concierge-chat') {
        $query = trim((string)($_POST['query'] ?? $_GET['query'] ?? ''));
        $city = trim((string)($_POST['city'] ?? $_GET['city'] ?? ''));
        if ($query === '') throw new InvalidArgumentException('Query is required.');

        // Search directory for relevant places
        $params = [':q' => '%' . text_substr($query, 0, 100) . '%'];
        $sql = "SELECT id, name, category, location, city, description FROM listings WHERE status='published' AND (name LIKE :q OR description LIKE :q OR tags LIKE :q OR category LIKE :q) LIMIT 5";
        $s = db()->prepare($sql);
        $s->execute($params);
        $places = $s->fetchAll();

        $context = "";
        foreach ($places as $p) {
            $context .= "- {$p['name']} ({$p['category']} in {$p['location']}): " . text_excerpt($p['description'], 120) . "\n";
        }

        $prompt = "User asks: \"{$query}\"\n"
                . ($city ? "Location context: {$city}\n" : "")
                . ($context ? "Matching Local Places in directory:\n{$context}\n" : "No specific listing found in directory.\n")
                . "Provide a friendly, conversational recommendation to the user.";

        $sys = "You are Guide AI, the welcoming local concierge for LocalGuide. Give helpful, authentic recommendations.";
        $aiRes = ai_generate_text($prompt, $sys, 0.7);

        json_response([
            'status' => 'ok',
            'data' => [
                'text' => $aiRes['text'],
                'provider' => $aiRes['provider'],
                'places' => array_map(fn($p) => [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'category' => $p['category'],
                    'location' => $p['location']
                ], $places)
            ]
        ]);
    }

    json_response(['status' => 'error', 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    json_response(['status' => 'error', 'message' => $e->getMessage()], 400);
}
