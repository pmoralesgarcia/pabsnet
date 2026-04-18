---
Title: Carly: Our Diva
Published: 2026-04-18T00:28:12.126Z
Author: Pablo Morales
Language: en
Layout: blog
Tag: 2026, 
Description: A tribute to a little diva!
Image: https://static.lifeofpablo.com/media/blog/2026-04-18-temp/carly4.jpg
Status: draft
---

![carly1.jpeg](https://static.lifeofpablo.com/media/blog/2026-04-18-carly-our-diva/carly1.jpg)

![carly2.jpeg](https://static.lifeofpablo.com/media/blog/2026-04-18-carly-our-diva/carly2.jpg)

<div class="gallery">
  <a href="image1.jpg"><img src="image1.jpg" alt="Description"></a>
  <a href="image2.jpg"><img src="image2.jpg" alt="Description"></a>
  </div>


<style>
.gallery {
  display: grid;
  /* Creates a 12-column layout for flexibility */
  grid-template-columns: repeat(12, 1fr);
  gap: 8px; /* Replaces your gutter logic */
  margin: 40px auto;
}

.gallery a {
  display: block;
  /* Maintains a consistent height without needing absolute positioning */
  height: 230px; 
  overflow: hidden;
  /* Defines the "column span" per image */
  grid-column: span 6; 
}

/* Modernizing the image handling */
.gallery img {
  width: 100%;
  height: 100%;
  object-fit: cover; /* Replaces the complex transform: translate logic */
  transition: transform 0.3s ease;
}

.gallery img:hover {
  transform: scale(1.05);
}

/* Example: Varying spans to match your original design */
.gallery a:nth-child(3),
.gallery a:nth-child(4),
.gallery a:nth-child(5) {
  grid-column: span 4;
}

.gallery a:nth-child(6) {
  grid-column: span 10;
}

.gallery a:nth-child(7) {
  grid-column: span 2;
}


/* Default desktop layout (12 columns) */
.gallery {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 8px;
  margin: 40px auto;
}

/* Mobile responsive override */
@media (max-width: 768px) {
  .gallery {
    grid-template-columns: repeat(1, 1fr); /* Force 1 column on mobile */
  }

  .gallery a {
    grid-column: span 1 !important; /* Force all items to take full width */
    height: 200px; /* Optional: adjust height for mobile */
  }
}
</style>
![carly3.jpeg](https://static.lifeofpablo.com/media/blog/2026-04-18-carly-our-diva/carly3.jpg)

![carly4.jpeg](https://static.lifeofpablo.com/media/blog/2026-04-18-carly-our-diva/carly4.jpg)

![carly5.jpeg](https://static.lifeofpablo.com/media/blog/2026-04-18-carly-our-diva/carly5.jpg)

![carly6.jpeg](https://static.lifeofpablo.com/media/blog/2026-04-18-carly-our-diva/carly6.jpg)