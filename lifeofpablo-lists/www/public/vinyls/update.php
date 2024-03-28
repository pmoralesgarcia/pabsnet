<?php

/**
 * List all users with a link to edit
 */

require "../../config.php";
require "../../common.php";

try {
  $connection = new PDO($dsn, $username, $password, $options);

  $sql = "SELECT * FROM vinyls";

  $statement = $connection->prepare($sql);
  $statement->execute();

  $result = $statement->fetchAll();
} catch(PDOException $error) {
  echo $sql . "<br>" . $error->getMessage();
}
?>
<?php require "../templates/header.php"; ?>
        
<h2>Update users</h2>
<div class="pa4">
  <div class="overflow-auto">
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Title</th>
            <th>Artist</th>
            <th>Genre</th>
            <th>Year</th>
            <th>Label</th>
            <th>ISBN</th>
            <th>Edit</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($result as $row) : ?>
        <tr>
            <td><?php echo escape($row["id"]); ?></td>
            <td><?php echo escape($row["title"]); ?></td>
            <td><?php echo escape($row["artist"]); ?></td>
            <td><?php echo escape($row["genre"]); ?></td>
            <td><?php echo escape($row["copyright_year"]); ?></td>
            <td><?php echo escape($row["label"]); ?></td>
            <td><?php echo escape($row["isbn"]); ?> </td>
            <td class="tag white is-light"><a href="update-single.php?id=<?php echo escape($row["id"]); ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
    </div>
    </div>
<a href="index.php">Back to home</a>

<?php require "../templates/footer.php"; ?>
