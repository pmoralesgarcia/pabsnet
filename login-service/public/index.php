<?php

require __DIR__ . '/../src/bootstrap.php';

use Login\Config;

Config::securityHeaders();
$url = Config::issuer();
page_start('Sign in with your website');
?>
<h1>Sign in with your website</h1>
<p class="lead">Let people sign in to your site using their own domain, with no passwords for you to store. Their website's <a href="https://indieauth.spec.indieweb.org/">IndieAuth</a> server confirms who they are, and this service tells your site the answer.</p>
<p><a class="button" href="/register">Register your site</a></p>

<h2>Add it to your site</h2>
<p>Create a random <code>state</code> and PKCE <code>code_verifier</code> in your site's session, then send the person here:</p>
<pre><code><?php echo e($url); ?>authorize
  ?client_id=https://example.com/
  &amp;redirect_uri=https://example.com/callback
  &amp;state=RANDOM
  &amp;code_challenge=BASE64URL_SHA256_OF_VERIFIER
  &amp;code_challenge_method=S256</code></pre>
<p>They come back to your <code>redirect_uri</code> with <code>code</code>, <code>state</code> and <code>iss</code>. Check that <code>state</code> matches and that <code>iss</code> is <code><?php echo e($url); ?></code>, then confirm the code from your server within <?php echo \Login\Store::CODE_TTL; ?> seconds:</p>
<pre><code>POST <?php echo e($url); ?>token
Content-Type: application/x-www-form-urlencoded
Accept: application/json

code=CODE&amp;client_id=https://example.com/
&amp;redirect_uri=https://example.com/callback
&amp;code_verifier=VERIFIER</code></pre>
<p>The answer is the verified website: <code>{"me": "https://person.example/"}</code></p>
<h2>Options</h2>
<ul>
    <li><code>me</code>: the website to sign in, if you already know it. Anyone else is refused.</li>
    <li><code>scope=profile</code>: also ask for their name and photo, if their server shares them.</li>
</ul>
<p class="hint">Your <code>redirect_uri</code> must be on the same origin as your <code>client_id</code>. Standard IndieAuth clients can also use this service: its metadata is at <a href="/.well-known/oauth-authorization-server">/.well-known/oauth-authorization-server</a>.</p>
<?php page_end();
