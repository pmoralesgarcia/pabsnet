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
            <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">#</th>
            <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Title</th>
            <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Artist</th>
            <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Location</th>
            <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Date</th>
            <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Edit</th>
            <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">On Website
        </tr>
    </thead>
    <tbody>
    <?php foreach ($result as $row) : ?>
        <tr>
            <td class="pv3 pr3 bb b--black-20"><?php echo escape($row["id"]); ?></td>
            <td class="pv3 pr3 bb b--black-20"><?php echo escape($row["title"]); ?></td>
            <td class="pv3 pr3 bb b--black-20"><?php echo escape($row["artist"]); ?></td>
            <td class="pv3 pr3 bb b--black-20"><?php echo escape($row["location"]); ?></td>
            <td class="pv3 pr3 bb b--black-20"><?php echo escape($row["date"]); ?></td>
            <td class="pv3 pr3 bb b--black-20"><a href="update-single.php?id=<?php echo escape($row["id"]); ?>">Edit</a></td>
            <td class="pv3 pr3 bb b--black-20"><a href="https://lifeofpablo.com/lists/music-listened#<?php echo escape($row["id"]); ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
    </div>
    </div>
<a href="index.php">Back to home</a>

<?php require "../templates/footer.php"; ?>
