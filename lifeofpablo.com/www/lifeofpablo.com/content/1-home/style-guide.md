---
Title: Style Guide
Published: 2023-11-24
Author: Pablo Morales
---

## This Style Guide is based on Datenstrom Paris (pablo.css) CSS file and Tachyons CSS. This page is going to change often!

### Transition Note
I am in the process of combining pablo.css and Tachyons CSS. It will be time to transition. This style guide in a way the vision for my site. 

### Navigation Bar
``` html
<div class="pv4">

<nav class="dt w-100 border-box pa3 ph5-ns">
  <a class="dtc v-mid mid-gray link dim w-25" href="#" title="Home">
    <img src="[image location]" class="dib w2 h2 br-100" alt="Site Name">
  </a>
  <div class="dtc v-mid w-75 tr">
    <a class="link dim dark-gray f6 f5-ns dib mr3 mr4-ns" href="#" title="About">Services</a>
    <a class="link dim dark-gray f6 f5-ns dib mr3 mr4-ns" href="#" title="Store">Blog</a>
    <a class="link dim dark-gray f6 f5-ns dib" href="#" title="Contact">Join Us</a>
  </div>
</nav>



```
### Border Radius
<div class="mb3 ph2 pv4 ba b--black-10 bg-washed-blue br0 w3 h3 dib">
          <code class="f6">.br0</code>
        </div>
<div class="mb3 ph2 pv4 ba b--black-10 bg-washed-blue br1 w3 h3 dib">
          <code class="f6">.br1</code>
        </div>
<div class="mb3 ph2 pv4 ba b--black-10 br2 w3 h3 dib">
          <code class="f6">.br2</code>
        </div>
``` css
br0 {        border-radius: 0; }
  .br1 {        border-radius: .125rem; }
  .br2 {        border-radius: .25rem; }
  .br3 {        border-radius: .5rem; }
  .br4 {        border-radius: 1rem; }
  .br-100 {     border-radius: 100%; }
  .br-pill {    border-radius: 9999px; }
  .br--bottom {
      border-top-left-radius: 0;
      border-top-right-radius: 0;
  }
  .br--top {
      border-bottom-left-radius: 0;
      border-bottom-right-radius: 0;
  }
  .br--right {
      border-top-left-radius: 0;
      border-bottom-left-radius: 0;
  }
  .br--left {
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
  }

```