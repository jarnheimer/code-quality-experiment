<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use InvalidArgumentException;

// --------------------- Infrastructure ---------------------
final class Config
{
    public static function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_NAME') ?: 'test',
        );
    }

    public static function username(): string
    {
        return getenv('DB_USER') ?: 'root';
    }

    public static function password(): string
    {
        return getenv('DB_PASS') ?: '';
    }
}

final class ConnectionFactory
{
    public static function make(): PDO
    {
        try {
            return new PDO(
                Config::dsn(),
                Config::username(),
                Config::password(),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ],
            );
        } catch (PDOException $e) {
            // In a real system log the exception ID and show a friendly page
            http_response_code(500);
            exit('Database unavailable.');
        }
    }
}

// --------------------- Domain ---------------------
final readonly class User
{
    public function __construct(public string $name) {}
}

final class UserValidator
{
    public function assert(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Name is required.');
        }
        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Name is too long (255 char max).');
        }
    }
}

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function add(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name) VALUES (:name)',
        );
        $stmt->bindValue(':name', $user->name);
        $stmt->execute();
    }

    /** @return list<User> */
    public function all(): array
    {
        $rows = $this->pdo
            ->query('SELECT name FROM users ORDER BY id DESC')
            ->fetchAll();

        return array_map(
            static fn(array $row): User => new User($row['name']),
            $rows,
        );
    }
}

// --------------------- Presentation ---------------------
final class Template
{
    /**
     * Ultra-light renderer (replace with Twig/Blade/etc. in real life).
     *
     * @param array<string,mixed> $data
     */
    public function render(string $body, array $data = []): string
    {
        foreach ($data as $key => $value) {
            $body = str_replace('{{ ' . $key . ' }}', (string) $value, $body);
        }

        return $body;
    }
}

final class Controller
{
    public function __construct(
        private UserRepository $repo,
        private UserValidator  $validator,
        private Template       $tpl,
    ) {}

    public function handle(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = (string) ($_POST['name'] ?? '');

            try {
                $this->validator->assert($name);
                $this->repo->add(new User($name));
                header('Location: /');           // Post/Redirect/Get
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        // Render view
        $items = array_map(
            static fn(User $u): string => '<li>' .
                htmlspecialchars($u->name, ENT_QUOTES, 'UTF-8') .
            '</li>',
            $this->repo->all(),
        );

        echo $this->tpl->render(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Users (senior)</title>
    <style>
        body { font-family: ui-sans-serif, sans-serif; margin: 2rem; }
        form { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php if ({{ error }}): ?>
        <p style="color:red;">{{ error }}</p>
    <?php endif; ?>

    <form method="post">
        <label>
            Name:
            <input type="text" name="name" required maxlength="255">
        </label>
        <button type="submit">Add</button>
    </form>

    <ul>
        {{ items }}
    </ul>
</body>
</html>
HTML,
            ['items' => implode("\n", $items), 'error' => $error],
        );
    }
}

// --------------------- Bootstrap ---------------------
// In real projects autoload via Composer; here we’re single-file for brevity.
$pdo        = ConnectionFactory::make();
$repo       = new UserRepository($pdo);
$validator  = new UserValidator();
$template   = new Template();
$controller = new Controller($repo, $validator, $template);
$controller->handle();
