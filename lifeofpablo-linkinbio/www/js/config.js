// js/config.js

async function fetchAndApplyPageConfig() {
  // IMPORTANT: Replace this with the actual URL of your page configuration API endpoint
  const configApiUrl = 'https://datasette.lifeofpablo.com/linkinbio/config.json?_shape=array'; 

  try {
    const response = await fetch(configApiUrl);
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    const configDataArray = await response.json();

    // The API JSON shows an array with one object, so take the first element
    if (!Array.isArray(configDataArray) || configDataArray.length === 0 || typeof configDataArray[0] !== 'object') {
      console.warn('Config API response is empty, not an array, or first element is not an object. Skipping page configuration.');
      return;
    }

    const config = configDataArray[0]; // Get the first (and only) configuration object

    // --- Update HTML Elements based on config object ---

    // 1. title-html changes the HTML title element
    if (config['title-html'] && typeof config['title-html'] === 'string') {
      document.title = config['title-html'];
    }

    // 2. page-icon changes the href for the link rel="icon"
    const pageIconLink = document.querySelector('link[rel="icon"]');
    if (pageIconLink && config['page-icon'] && typeof config['page-icon'] === 'string') {
      pageIconLink.href = config['page-icon'];
    }

    // 3. description-meta changes the description meta
    const metaDescription = document.querySelector('meta[name="description"]');
    if (metaDescription && config['description-meta'] && typeof config['description-meta'] === 'string') {
      metaDescription.content = config['description-meta'];
    }

    // 4. keywords updates the keywords meta
    const metaKeywords = document.querySelector('meta[name="keywords"]');
    if (metaKeywords && config['keywords'] && typeof config['keywords'] === 'string') {
      metaKeywords.content = config['keywords'];
    }

    // 5. canonical updates the link rel="canonical"
    const canonicalLink = document.querySelector('link[rel="canonical"]');
    if (canonicalLink && config['canonical'] && typeof config['canonical'] === 'string') {
      canonicalLink.href = config['canonical'];
    }

    // 6. author updates the author meta
    const metaAuthor = document.querySelector('meta[name="author"]');
    if (metaAuthor && config['author'] && typeof config['author'] === 'string') {
      metaAuthor.content = config['author'];
    }

    // 7. avatar-img updates the avatar image in the body
    const avatarImg = document.querySelector('img.avatar');
    if (avatarImg && config['avatar-img'] && typeof config['avatar-img'] === 'string') {
      avatarImg.src = config['avatar-img'];
      // If you're using an API for the avatar, you might want to remove srcset
      // if the API doesn't provide a @2x version or if it's an absolute URL.
      avatarImg.removeAttribute('srcset'); 
    }

    // 8. avatar-alt provides the avatar alt text
    if (avatarImg && config['avatar-alt'] && typeof config['avatar-alt'] === 'string') {
      avatarImg.alt = config['avatar-alt'];
    }

    // 9. header-h1 updates the h1 in the body
    const headerH1Div = document.querySelector('h1 div'); // Targeting the div inside h1
    if (headerH1Div && config['header-h1'] && typeof config['header-h1'] === 'string') {
      headerH1Div.textContent = config['header-h1'];
    }

    // 10. description-page updates the <p> under the h1 in the body
    const descriptionP = document.querySelector('h1 + p'); // Selects the <p> tag immediately following <h1>
    if (descriptionP && config['description-page'] && typeof config['description-page'] === 'string') {
      descriptionP.textContent = config['description-page'];
    }

    // 11. privacy-policy updates the privacy policy link in the body (in the footer)
    // Assuming the privacy policy link is the first <a> tag in the footer
    const privacyLink = document.querySelector('footer a'); 
    if (privacyLink && config['privacy-policy'] && typeof config['privacy-policy'] === 'string') {
      privacyLink.href = config['privacy-policy'];
    }

  } catch (error) {
    console.error('Failed to fetch or apply page configuration:', error);
    // Optionally display a user-friendly error message on the page
  }
}

// Run the configuration function when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', fetchAndApplyPageConfig);