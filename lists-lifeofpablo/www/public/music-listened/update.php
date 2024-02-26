<?php

/**
 * List all users with a link to edit
 */

require "../../config-lists.php";
require "../../common.php";

try {
  $connection = new PDO($dsn, $username, $password, $options);

  $sql = "SELECT * FROM music_listened ORDER BY date DESC";

  $statement = $connection->prepare($sql);
  $statement->execute();

  $result = $statement->fetchAll();
} catch(PDOException $error) {
  echo $sql . "<br>" . $error->getMessage();
}
?>
<?php require "../templates/header.php"; ?>
        
<h2>View Songs Listened</h2>
<div class="pa1">
  <div class="overflow-auto">
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Title</th>
            <th>Artist</th>
            <th>Location</th>
            <th>Date</th>
            <th>Edit</th>
            <th>On Website
        </tr>
    </thead>
    <tbody>
    <?php foreach ($result as $row) : ?>
        <tr>
            <td><?php echo escape($row["id"]); ?></td>
            <td><?php echo escape($row["title"]); ?></td>
            <td><?php echo escape($row["artist"]); ?></td>
            <td><?php echo escape($row["location"]); ?></td>
            <td><?php echo escape($row["date"]); ?></td>
            <td class=""><a href="update-single.php?id=<?php echo escape($row["id"]); ?>">Edit</a></td>
            <td><a href="https://lifeofpablo.com/lists/music-listened#<?php echo escape($row["id"]); ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
    </div>
    </div>
<a href="index.php">Back to home</a>

<?php require "../templates/footer.php"; ?>
