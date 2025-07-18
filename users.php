<?php
$conn = mysqli_connect("localhost", "root", "", "test");
if (!$conn) {
    die("Can't connect to db");   // ☠ Kills the page, leaks detail in prod
}

if (isset($_POST['name'])) {
    $name = $_POST['name'];       // ☠ Unsanitised
    $sql  = "INSERT INTO users (name) VALUES ('$name')"; // ☠ Injection
    mysqli_query($conn, $sql);    // ☠ No error handling
}

$result = mysqli_query($conn, "SELECT * FROM users");   // ☠ SELECT *
?>
<!DOCTYPE html>
<html>
<head><title>Users</title></head>
<body>
<form method="post">
    Name: <input type="text" name="name">
    <input type="submit" value="Add">
</form>

<ul>
<?php while ($row = mysqli_fetch_assoc($result)) { ?>
    <li><?= $row['name'] ?></li>   <!-- ☠ XSS -->
<?php } ?>
</ul>
</body>
</html>
