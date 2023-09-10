<?php

/**
 * Use an HTML form to create a new entry in the
 * users table.
 *
 */

require "../gb-config/config.php";
require "../gb-config/common.php";

if (isset($_POST['submit'])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) die();

  try  {
    $connection = new PDO($dsn, $username, $password, $options);
    
    $new_entry = array(
      "name" => $_POST['name'],
      "website"     => $_POST['website'],
      "website"       => $_POST['website'],
      "message"  => $_POST['message']
    );

    $sql = sprintf(
      "INSERT INTO %s (%s) values (%s)",
      "guestbook",
      implode(", ", array_keys($new_entry)),
      ":" . implode(", :", array_keys($new_entry))
    );
    
    $statement = $connection->prepare($sql);
    $statement->execute($new_entry);
  } catch(PDOException $error) {
      echo $sql . "<br>" . $error->getMessage();
  }
}
?>
<?php require "templates/header.php"; ?>

  <?php if (isset($_POST['submit']) && $statement) : ?>
    <blockquote><?php echo escape($_POST['name']); ?> successfully added.</blockquote>
  <?php endif; ?>

  <h2>Write in the Guestbook</h2>

  <form method="post">
    <input name="csrf" type="hidden" value="<?php echo escape($_SESSION['csrf']); ?>">
    <label for="name">Name</label>
    <input required type="text" name="name" id="name">
<br>
    <label for="website">Website</label>
    <input readonly type="text" name="website" value="<?php echo $_SERVER['REMOTE_USER']; ?>"id="website">
<br>
    <label for="message">Message</label>
    <textarea required name="message" id="message"></textarea>
<br>
    <input type="submit" name="submit" value="Submit">
  </form>

  <a href="index.php">Back to home</a>

<?php require "templates/footer.php"; ?>
