---
Title: Test
---


<div class="box green"></div>
<div class="box purple"></div>
<div class="box blue"></div>

<style>


/* Global styles come from external css https://codepen.io/GreenSock/pen/gOWxmWG.css*/

body {
  display: flex;
  align-items: center;
  justify-content: space-around;
  min-height: 100vh;
  flex-direction: column;
}


    </style>

<script>


// target the element with a class of "green" - rotate and move TO 100px to the left over the course of 1 second. 
gsap.to(".green", {rotation: 360, x: 100, duration: 1});

// target the element with a class of "purple" - rotate and move FROM 100px to the left over the course of 1 second. 
gsap.from(".purple", {rotation: -360, x: -100, duration: 1});

// target the element with a class of "blue" - rotate and move FROM 100px to the left, TO 100px to the right over the course of 1 second. 
gsap.fromTo(".blue", {x: -100},{rotation: 360, x: 100, duration: 1});

</script>