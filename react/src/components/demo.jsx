import { useEffect } from 'react';

export default function Demo() {
  useEffect(() => {
    // Your API endpoint URL
    const apiUrl = 'https://api.altly.io/analyze/image';

    // Your license key
    const licenseKey = '1f446e9e-f40f-4097-acff-bc6fec8be655';

    // The data you want to send in the request body
    const requestData = {
      imageUrl:
        'https://www.woodfordreserve.com/wp-content/plugins/bf-wp-js-agegate/img/bottles/Bourbon-Bottle.png',
    };

    // Create the headers with the license key
    const headers = new Headers({
      'Content-Type': 'application/json',
      'license-key': licenseKey,
    });

    // Create the request object
    const request = new Request(apiUrl, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(requestData),
    });

    // Now, let's fetch the data
    fetch(request)
      .then((response) => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json(); // Parse the response JSON
      })
      .then((data) => {
        // Do something with the data here, like logging it or processing it
        console.log(data);
      })
      .catch((error) => {
        // Handle any errors that occur during the fetch
        console.error('There was a problem with the fetch operation:', error);
      });
  }, []);
}
