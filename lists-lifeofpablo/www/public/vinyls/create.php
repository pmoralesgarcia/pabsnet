<?php

/**
 * Use an HTML form to create a new entry in the
 * users table.
 *
 */

 require "../../config.php";
 require "../../common.php";

if (isset($_POST['submit'])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) die();

  try  {
    $connection = new PDO($dsn, $username, $password, $options);
    
    $new_user = array(
      "firstname" => $_POST['firstname'],
      "lastname"  => $_POST['lastname'],
      "email"     => $_POST['email'],
      "age"       => $_POST['age'],
      "location"  => $_POST['location']
    );

    $sql = sprintf(
      "INSERT INTO %s (%s) values (%s)",
      "users",
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
    <blockquote><?php echo escape($_POST['firstname']); ?> successfully added.</blockquote>
  <?php endif; ?>

  <h2>Add a Vinyl</h2>

  <form class="pa4 black-80" method="post">
    <div class="measure">
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" name="csrf" type="hidden" value="<?php echo escape($_SESSION['csrf']); ?>">
    <label class="f6 b db mb2" for="firstname">First Name</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="firstname" id="firstname">
    <label class="f6 b db mb2" for="lastname">Last Name</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="lastname" id="lastname">
    <label class="f6 b db mb2" for="email">Email Address</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="email" id="email">
    <label class="f6 b db mb2" for="age">Age</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="age" id="age">
    <label class="f6 b db mb2" for="location">Location</label>
    <input class="input-reset ba b--black-20 pa2 mb2 db w-100" type="text" name="location" id="location">
    <input class="button" type="submit" name="submit" value="Submit">
  </div>
  </form>
  
  <a href="index.php">Back to home</a>

<?php require "../templates/footer.php"; ?>
