<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("Not found\n"); }
require_once dirname(__DIR__) . '/app/bootstrap.php';

function prompt_line(string $label): string {
    fwrite(STDOUT, $label);
    return trim((string)fgets(STDIN));
}
function prompt_secret(string $label): string {
    if (PHP_OS_FAMILY === 'Windows' && function_exists('proc_open')) {
        $ps = '$s=Read-Host -Prompt ' . "'" . addslashes($label) . "'" . ' -AsSecureString; $b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); try { [Console]::Write([Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes([Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)))) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b) }';
        $proc = @proc_open(['powershell.exe','-NoProfile','-Command',$ps], [0=>STDIN,1=>['pipe','w'],2=>STDERR], $pipes);
        if (is_resource($proc)) {
            $encoded = stream_get_contents($pipes[1]); fclose($pipes[1]); $status = proc_close($proc);
            $decoded = base64_decode(trim((string)$encoded), true);
            if ($status === 0 && $decoded !== false) return $decoded;
        }
    }
    fwrite(STDOUT, $label . ' (input may be visible): ');
    return rtrim((string)fgets(STDIN), "\r\n");
}

$admins = db()->query("SELECT id,name,username,email FROM users WHERE role='admin' AND is_active=1 ORDER BY id ASC")->fetchAll();
if (!$admins) { fwrite(STDERR, "No active administrator was found. Create the first administrator through the invite-only portal after generating an invitation token.\n"); exit(1); }
fwrite(STDOUT, "Local Guide administrator credential updater\n\n");
foreach ($admins as $admin) fwrite(STDOUT, sprintf("ID %d | %s | @%s | %s\n", (int)$admin['id'], $admin['name'], $admin['username'], $admin['email']));
$defaultId = (int)$admins[0]['id'];
$choice = prompt_line("Admin ID to update [{$defaultId}]: ");
$id = $choice === '' ? $defaultId : (int)$choice;
$target = null; foreach ($admins as $admin) if ((int)$admin['id'] === $id) $target = $admin;
if (!$target) { fwrite(STDERR, "That administrator ID was not found. No changes made.\n"); exit(1); }
$username = prompt_line('New administrator username: ');
if (!valid_username($username)) { fwrite(STDERR, "Invalid username. Use 3–30 letters, numbers, dots, underscores or hyphens; reserved names are blocked.\n"); exit(1); }
if (username_exists($username, $id)) { fwrite(STDERR, "That username is already in use. No changes made.\n"); exit(1); }
$password = prompt_secret('New administrator password');
$confirm = prompt_secret('Confirm new administrator password');
if (strlen($password) < 12 || !hash_equals($password, $confirm)) { fwrite(STDERR, "Password must be at least 12 characters and both entries must match. No changes made.\n"); exit(1); }
$update = db()->prepare("UPDATE users SET username=:username,password_hash=:hash WHERE id=:id AND role='admin'");
$update->execute([':username'=>$username, ':hash'=>password_hash($password, PASSWORD_DEFAULT), ':id'=>$id]);
if ($update->rowCount() !== 1) { fwrite(STDERR, "No account was changed.\n"); exit(1); }
fwrite(STDOUT, "\nAdministrator login updated successfully. The password was stored as a one-way password hash.\n");
fwrite(STDOUT, "Generate or use a private invitation URL with generate-admin-invite.bat, then sign in through /admin/hidden-portal-auth/.\n");
