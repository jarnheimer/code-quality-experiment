<?php
declare(strict_types=1);

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        // A real app would read DSN/creds from env-vars or a config file
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=test;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    public function add(string $name): void
    {
        $this->pdo
            ->prepare('INSERT INTO users (name) VALUES (:name)')
            ->execute(['name' => $name]);
    }

    /** @return list<array{name:string}> */
    public function all(): array
    {
        return $this->pdo
            ->query('SELECT name FROM users ORDER BY id DESC')
            ->fetchAll();
    }
}

$repo = new UserRepository();

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $repo->add(trim($_POST['name']));
    header('Location: ' . $_SERVER['PHP_SELF']); // PRG pattern
    exit;
}

$users = $repo->all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Users (mid)</title>
</head>
<body>
<form method="post">
    <label>
        Name:
        <input type="text" name="name" required>
    </label>
    <button type="submit">Add</button>
</form>

<ul>
    <?php foreach ($users as $user): ?>
        <li><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
</ul>
</body>
</html>
