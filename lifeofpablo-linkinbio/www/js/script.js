// js/script.js

// Function to fetch data from the API and populate the HTML links
async function fetchAndPopulateLinks() {
  // IMPORTANT: Replace this with the actual URL of your API endpoint
  const apiUrl = 'http://localhost:8001/linkinbio/links.json?_shape=array'; 

  const linksContainer = document.querySelector('.button-stack');
  if (!linksContainer) {
    console.error('Error: .button-stack container not found in the HTML. Cannot populate links.');
    return;
  }

  // Display a temporary loading message
  linksContainer.innerHTML = '<p style="text-align: center; color: #888;">Loading your awesome links...</p>'; 

  try {
    // 1. Fetch data from the API
    const response = await fetch(apiUrl);

    // 2. Check if the request was successful
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    // 3. Parse the JSON response
    let jsonData = await response.json(); // Use 'let' because we'll reassign after sorting

    // Ensure jsonData is an array and not empty
    if (!Array.isArray(jsonData) || jsonData.length === 0) {
      linksContainer.innerHTML = '<p style="text-align: center; color: #888;">No links available.</p>';
      console.warn('API response is empty or not an array:', jsonData);
      return;
    }

    // --- Sorting Logic based on 'order' column ---
    jsonData.sort((a, b) => {
      // Coerce empty string to null for consistent sorting logic
      const orderA = a.order === "" ? null : a.order;
      const orderB = b.order === "" ? null : b.order;

      // Handle nulls/empty strings first: they should come after numbers
      if (orderA === null && orderB !== null) {
        return 1; // 'a' (null/empty string) comes after 'b' (number)
      }
      if (orderA !== null && orderB === null) {
        return -1; // 'a' (number) comes before 'b' (null/empty string)
      }
      if (orderA === null && orderB === null) {
        return 0; // Both are null/empty string, maintain original relative order
      }

      // If both are numbers, sort numerically
      return orderA - orderB;
    });
    // --- End of Sorting Logic ---

    // Clear the loading message before adding the actual links
    linksContainer.innerHTML = '';

    // Define services that typically use 'button-default' in LittleLink
    // Based on common LittleLink usage for generic icons/services
    const defaultButtonServices = [
      'generic', 'blog', 'calendar', 'cloud', 'code', 'computer', 'email', 'homepage', 
      'map', 'phone', 'review', 'rss', 'shopping-bag', 'shopping-tag', 'sms', 'website',
      'mail' // For generic-mail.svg, though 'email' is preferred
    ];

    // 4. Loop through each item in the sorted JSON data and create HTML elements
    jsonData.forEach(data => {
      const anchor = document.createElement('a');

      // Add common class 'button'
      anchor.classList.add('button');

      // Determine the specific button class based on 'service'
      const serviceLowerCase = typeof data.service === 'string' ? data.service.toLowerCase().replace(/\s+/g, '-') : '';

      if (defaultButtonServices.includes(serviceLowerCase)) {
        anchor.classList.add('button-default'); // Use default class for generic services
      } else if (serviceLowerCase) {
        anchor.classList.add(`button-${serviceLowerCase}`); // Use specific brand class
      } else {
        anchor.classList.add('button-default'); // Fallback if service is empty/invalid
      }

      // Set href link using 'url' from JSON
      anchor.href = typeof data.url === 'string' && data.url ? data.url : '#';
      // Ensure target and rel are set for external links, or adjust if you have internal links
      anchor.target = '_blank';
      anchor.rel = 'noopener';
      anchor.role = 'button';

      // Create the <img> element for the icon
      const icon = document.createElement('img');
      icon.classList.add('icon');
      icon.setAttribute('aria-hidden', 'true');

      // Construct icon src path: check if it's already a full path or just a filename
      let iconSrc = 'images/icons/default.svg'; // Default fallback icon
      if (typeof data.icon === 'string' && data.icon) {
        if (data.icon.startsWith('images/icons/')) {
          iconSrc = data.icon; // Already a full path
        } else {
          iconSrc = `images/icons/${data.icon}`; // Prepend base path
        }
      }
      icon.src = iconSrc;

      // Alt text for icon, using the 'name' or 'service'
      icon.alt = (typeof data.name === 'string' && data.name ? data.name : typeof data.service === 'string' && data.service ? data.service : 'Link') + ' Logo';

      anchor.appendChild(icon);

      // Set the text content using the 'name' property
      const textContent = typeof data.name === 'string' && data.name
                          ? data.name
                          : typeof data.service === 'string' && data.service // Fallback to service if name is missing
                            ? data.service.charAt(0).toUpperCase() + data.service.slice(1)
                            : 'Link'; // Final fallback text
      const textNode = document.createTextNode(textContent);
      anchor.appendChild(textNode);

      // Append the completed anchor element to the container
      linksContainer.appendChild(anchor);
    });

  } catch (error) {
    // Handle any errors during the fetch operation
    console.error('Failed to fetch or process links:', error);
    linksContainer.innerHTML = '<p style="text-align: center; color: #dc3545;">Error loading links. Please try again later.</p>';
  }
}

// Call the function when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', fetchAndPopulateLinks);