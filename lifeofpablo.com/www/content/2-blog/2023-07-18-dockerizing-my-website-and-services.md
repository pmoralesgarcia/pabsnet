---
Title: Dockerizing my website and services
Published: 2023-07-18 11:20:19
Author: Pablo Morales
Layout: blog
Tag: docker, self-hosted, vouch-proxy, containers
Image: https://static.lifeofpablo.com/media/images/horizontal-logo-monochromatic-white.png
---
[image https://static.lifeofpablo.com/media/images/horizontal-logo-monochromatic-white.png]

This week I started using Docker (again). I have used it one or twice through out the years. My first time being in 2015. I was so opposed or timid in the use of using Docker. I was so used to doing things the "hard way" otherwise known as manual install. So My friend Mani, has been showing me his Kubernetes setup and talking about Docker. I thought it was pretty rad! Just the way things flowed and how easy it is to stop and start a container. This is something he has started for a while. I hope to become well versed in this. 

This leads to what is mentioned in the title of this post. I am in the process of learning to understand containers, Kubernetes, miniKube, docker and how this all ties together. I'll start with Docker and learn Kubernetes side-by-side and hope to understand. The goal is to containerize all my websites and services to keep up with the times and become a better developer. 

My first successful implementation of a docker container was, wait for it..... Can you guess what it is? If you guessed <span class="vp" markdown="1">[image vp1.png]</span> [Vouch Proxy](https://github.com/vouch/vouch-proxy)! You win an cookie 🍪! Hooray! For the record, not a browser cookie. Those are not tasty or pleasing. 

It was pretty simple once I figured out how to run the commands to adjust to my needs. I used my existing configuration file for Vouch Proxy but with some redactions. So, if you are seeing this post, or signed into my website, vouch proxy is running on docker!

Next step is my [website](https://lifeofpablo,com) which runs on php and uses Datenstrom Yellow as the flat-file cms.

I am excited for this new phase of my developer life. I have so much to learn! Shout out to Mani for leading me in this direction. 


<style>
.vp img {
height: 1em;
width: auto;
}
</style>
