<?php

/**
 * Use an HTML form to create a new entry in the
 * users table.
 *
 */

 require "../../config-lists.php";
 require "../../common.php";

if (isset($_POST['submit'])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) die();

  try  {
    $connection = new PDO($dsn, $username, $password, $options);
    
    $new_user = array(
      "title" => $_POST['title'],
      "artist"  => $_POST['artist'],
      "location"     => $_POST['location']
    );

    $sql = sprintf(
      "INSERT INTO %s (%s) values (%s)",
      "music_listened",
      implode(", ", array_keys($new_user)),
      ":" . implode(", :", array_keys($new_user))
    );
    
    $statement = $connection->prepare($sql);
    $statement->execute($new_user);
  } catch(PDOException $error) {
      echo $sql . "<br>" . $error->getMessage();
  }
}
?>
<?php require "../templates/header.php"; ?>

  <?php if (isset($_POST['submit']) && $statement) : ?>
    <blockquote><?php echo escape($_POST['title']); ?> successfully added.</blockquote>
  <?php endif; ?>

  <h2>Add a Song</h2>

  <form class="pa4 black-80" method="post">
    <div class="measure">
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" name="csrf" type="hidden" value="<?php echo escape($_SESSION['csrf']); ?>">
    <label class="f6 b db mb2" for="title">Title</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="title" id="title">
    <label class="f6 b db mb2" for="artist">artist</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="artist" id="artist">
    <label class="f6 b db mb2" for="email">Location</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="location" id="location">
    <input class="button" type="submit" name="submit" value="Submit">
  </div>
  </form>
  
  <a href="index.php">Back to home</a>

<?php require "../templates/footer.php"; ?>
