---
Title: Hiking with Friends
Published: 2026-03-16 21:44:15
Author: Pablo Morales
Layout: blog
Tag: 2026, hiking, friends, adventure, pie, friendships
Description: Hikes are fun but even more fun with Friends.
Image: https://static.lifeofpablo.com/media/hike-with-friends/flowers-on-hike.jpg 
ImageAlt: Flowers I saw on my hike
---
Over the past year, I went on a number of hikes that I lost count. I've been going to places in Northern California primarily. It's been nice hanging with friends and reconnecting with them. It's nice to get away from the concrete jungle of San Francisco. I love spending time in SF but nature is nurturing. 

Nothing like eating some Cherry Pie on the beach as a reward for making it through that hike or getting a beer afterwards. It's the little things of life. 

What are you favorite places to go hike?

<div class="CSSgal">
  <s id="s1"></s> <s id="s2"></s> <s id="s3"></s> <s id="s4"></s>

  <div class="slider">
    <div class="slide-item">
        <div class="img-container" style="background-image: url('https://static.lifeofpablo.com/media/hike-with-friends/IMG_6613.jpeg');"></div>
        <div class="caption">This view is amazing.</div>
    </div>
    <div class="slide-item">
        <div class="img-container" style="background-image: url('https://static.lifeofpablo.com/media/hike-with-friends/IMG_6615.jpeg');"></div>
        <div class="caption">I love the beach.</div>
    </div>
    <div class="slide-item">
        <div class="img-container" style="background-image: url('https://static.lifeofpablo.com/media/hike-with-friends/IMG_6616.jpeg');"></div>
        <div class="caption">Eating some cherry pie at the end of the hike.</div>
    </div>
    <div class="slide-item">
        <div class="img-container" style="background-image: url('https://static.lifeofpablo.com/media/hike-with-friends/IMG_7571.jpeg');"></div>
        <div class="caption">The yellow color pops.</div>
    </div>
  </div>
  
  <div class="prevNext">
    <div><a href="#s4">‹</a><a href="#s2">›</a></div>
    <div><a href="#s1">‹</a><a href="#s3">›</a></div>
    <div><a href="#s2">‹</a><a href="#s4">›</a></div>
    <div><a href="#s3">‹</a><a href="#s1">›</a></div>
  </div>

  <div class="bullets">
    <a href="#s1">1</a> <a href="#s2">2</a> <a href="#s3">3</a> <a href="#s4">4</a>
  </div>
</div>

<style>
/* CONTAINER */
.CSSgal {
    position: relative;
    overflow: hidden;
    height: 450px; /* Increased height to accommodate captions */
    background: #111;
}

/* SLIDER ENGINE */
.CSSgal .slider {
    height: 100%;
    white-space: nowrap;
    transition: transform 0.8s ease-in-out;
}

.CSSgal .slide-item {
    display: inline-flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    vertical-align: top;
    white-space: normal;
}

/* THE IMAGE AREA */
.CSSgal .img-container {
    flex-grow: 1; /* Takes up all available space except the caption */
    background: no-repeat center center;
    background-size: cover;
}

/* THE CAPTION AREA */
.CSSgal .caption {
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 15px;
    text-align: center;
    font-size: 0.95rem;
    font-family: sans-serif;
}

/* NAVIGATION LOGIC */
#s1:target ~ .slider { transform: translateX(0%); }
#s2:target ~ .slider { transform: translateX(-100%); }
#s3:target ~ .slider { transform: translateX(-200%); }
#s4:target ~ .slider { transform: translateX(-300%); }

/* ARROWS */
.CSSgal .prevNext {
    position: absolute;
    z-index: 1;
    top: 40%; /* Moved up slightly to center on the image, not the whole slide */
    width: 100%;
    height: 0;
}
.CSSgal .prevNext div { visibility: hidden; }
#s1:target ~ .prevNext div:nth-child(1),
#s2:target ~ .prevNext div:nth-child(2),
#s3:target ~ .prevNext div:nth-child(3),
#s4:target ~ .prevNext div:nth-child(4),
.CSSgal .prevNext div:first-child { visibility: visible; }

.CSSgal .prevNext a {
    background: rgba(255,255,255,0.8);
    position: absolute;
    width: 40px;
    height: 40px;
    line-height: 38px;
    text-align: center;
    text-decoration: none;
    color: #000;
    border-radius: 50%;
    font-size: 24px;
}
.CSSgal .prevNext a:last-child { right: 0; }

/* BULLETS / DOTS */
.CSSgal .bullets {
    position: absolute;
    bottom: 60px; /* Placed just above the caption bar */
    width: 100%;
    text-align: center;
}
.CSSgal .bullets a {
    display: inline-block;
    width: 10px;
    height: 10px;
    background: rgba(255,255,255,0.4);
    text-indent: -9999px;
    margin: 0 4px;
    border-radius: 50%;
}
#s1:target ~ .bullets a:nth-child(1),
#s2:target ~ .bullets a:nth-child(2),
#s3:target ~ .bullets a:nth-child(3),
#s4:target ~ .bullets a:nth-child(4) { background: #fff; }

</style>
