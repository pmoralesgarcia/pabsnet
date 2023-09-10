<?php

/**
 * Delete a user
 */

require "../gb-config/config.php";
require "../gb-config/common.php";

$success = null;

if (isset($_POST["submit"])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) die();

  try {
    $connection = new PDO($dsn, $username, $password, $options);
  
    $id = $_POST["submit"];

    $sql = "DELETE FROM guestbook WHERE id = :id";

    $statement = $connection->prepare($sql);
    $statement->bindValue(':id', $id);
    $statement->execute();

    $success = "Entry successfully deleted";
  } catch(PDOException $error) {
    echo $sql . "<br>" . $error->getMessage();
  }
}

try {
  $connection = new PDO($dsn, $username, $password, $options);

  $sql = "SELECT * FROM guestbook";

  $statement = $connection->prepare($sql);
  $statement->execute();

  $result = $statement->fetchAll();
} catch(PDOException $error) {
  echo $sql . "<br>" . $error->getMessage();
}
?>
<?php require "templates/header.php"; ?>
        
<h2>Delete Entries</h2>

<?php if ($success) echo $success; ?>

<form method="post">
  <input name="csrf" type="hidden" value="<?php echo escape($_SESSION['csrf']); ?>">
  <table>
    <thead>
      <tr>
            <th>#</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Website</th>
            <th>Message</th>
            <th>Date</th>
        <th>Delete</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($result as $row) : ?>
      <tr>
        <td><?php echo escape($row["id"]); ?></td>
        <td><?php echo escape($row["firstname"]); ?></td>
        <td><?php echo escape($row["lastname"]); ?></td>
        <td><?php echo escape($row["website"]); ?></td>
        <td><?php echo escape($row["message"]); ?></td>
        <td><?php echo escape($row["date"]); ?> </td>
        <td><button type="submit" name="submit" value="<?php echo escape($row["id"]); ?>">Delete</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</form>

<a href="index.php">Back to home</a>

<?php require "templates/footer.php"; ?>
