<?php if(isset($_SERVER['REMOTE_USER'])) {
  echo '<p>Logged in as</p>';
  echo '<p>' . $_SESSION['username'] . '</p>';
  echo '<p><a href="https://auth.lifeofpablo.com/logout">Log Out</a></p>';
  die();
}

if(!isset($_SERVER['REMOTE_USER'])) {
  $authorize_url = 'TODO';
  echo '<p>Not logged in</p>';
  echo '<p><a href="https://auth.lifeofpablo.com/login?url=https://lifeofpablo.com/">Log In</a></p>';
}

?>
