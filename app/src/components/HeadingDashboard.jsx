import { useEffect, useState } from 'react';

import ProgressBar from '../components/ProgressBar';
import getBaseUrl from '../helpers/baseUrlHelper';
import { getLicenseKey } from '../helpers/util';

export default function HeadingDashboard({
  totalCreditsRemaining,
  imagesMissingAltText,
}) {
  const [isGenerating, setIsGenerating] = useState(false);
  const [isScanning, setIsScanning] = useState(false);
  const [progress, setProgress] = useState(0);
  const [licenseKey, setLicenseKey] = useState(false);

  // Set license Key so as to use it to disabled the Bulk Generate Button.
  useEffect(() => {
    (async () => {
      const response = await getLicenseKey();
      setLicenseKey(response);
    })();
  }, []);

  const fetchImages = () => {
    handleScanImagesClick();
    fetch(getBaseUrl() + '/wp-json/altly/v1/bulk-generate', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
      .then((response) => response.json())
      .then((data) => {
        processImages(data);
      })
      .catch((error) => console.error('Error:', error));
  };

  // const processImagesv1 = async (images) => {
  //   const apiKey = await getLicenseKey();

  //   images.forEach(image => {
  //     const images = [
  //       {
  //         url: image.url, // Assuming there's a variable imageUrl that holds the image URL
  //         api_endpoint: image.api_endpoint, // Assuming there's a variable apiUrl that holds the API endpoint URL
  //         asset_id: image.asset_id, // Assuming there's a variable assetId that holds the asset ID
  //         transaction_id: image.transaction_id, // Assuming there's a variable processingId that holds the processing ID
  //         platform_name: "WordPress"
  //       }
  //     ];

  //     const jsonBody = JSON.stringify({ images: images });

  //     fetch('https://api.altly.io/v1/batch/queue', {
  //       method: 'POST',
  //       headers: {
  //         'Content-Type': 'application/json',
  //         'Authorization': 'Bearer ' + apiKey,
  //       },

  //       body: jsonBody,
  //     })
  //     .then(response => response.json())
  //     .then(data => console.log('Success:', data))
  //     .catch(error => console.error('Error:', error));
  //   });
  // };

  // const processImagesv2 = async (images) => {
  //   const apiKey = await getLicenseKey();

  //   // Function to introduce a delay
  //   const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  //   for (const image of images) {
  //     const imagesArray = [
  //       {
  //         url: image.url,
  //         api_endpoint: image.api_endpoint,
  //         asset_id: image.asset_id,
  //         transaction_id: image.transaction_id,
  //         platform_name: "WordPress"
  //       }
  //     ];

  //     const jsonBody = JSON.stringify({ images: imagesArray });

  //     try {
  //       const response = await fetch('https://api.altly.io/v1/batch/queue', {
  //         method: 'POST',
  //         headers: {
  //           'Content-Type': 'application/json',
  //           'Authorization': 'Bearer ' + apiKey,
  //         },
  //         body: jsonBody,
  //       });

  //       const data = await response.json();
  //       // console.log('Success:', data);

  //       // Introduce a delay before proceeding to the next image
  //       // Adjust the delay time as needed (1000ms is 1 second)
  //       await delay(50);
  //     } catch (error) {
  //       console.error('Error:', error);
  //     }
  //   }
  // };

  const processImages = async (
    images,
    batchSize = 10,
    delayDuration = 1000
  ) => {
    for (let i = 0; i < images.length; i += batchSize) {
      const batch = images.slice(i, i + batchSize);
      const promises = batch.map((image) => sendImageProcessingRequest(image));

      try {
        await Promise.all(promises);
        // console.log('Batch success:', results);
      } catch (error) {
        // console.error('Batch error:', error);
        // Implement retry logic here if necessary
      }

      // Wait before sending the next batch
      await delay(delayDuration);
    }
  };

  const sendImageProcessingRequest = async (image) => {
    const requestBody = JSON.stringify({
      images: [
        {
          url: image.url,
          api_endpoint: image.api_endpoint,
          asset_id: image.asset_id,
          transaction_id: image.transaction_id,
          platform_name: 'WordPress',
        },
      ],
    });

    const response = await fetch('https://api.altly.io/v1/batch/queue', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${licenseKey}`,
      },
      body: requestBody,
    });

    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

    return response.json();
  };

  const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  const handleScanImagesClick = () => {
    if (!isScanning) {
      setIsScanning(true);
      setProgress(0); // Reset progress when Scan Images is clicked

      const totalSteps = 100; // You can adjust this based on your desired granularity
      const timeInterval = 2000 / totalSteps;

      const intervalId = setInterval(() => {
        setProgress((prevProgress) => {
          const newProgress = prevProgress + 1;
          if (newProgress === totalSteps) {
            clearInterval(intervalId);
            setIsScanning(false);
          }
          return newProgress;
        });
      }, timeInterval);
    }
  };

  const handleCancelClick = () => {
    // Handle canceling the bulk generate process here
    setIsGenerating(false);
    setProgress(0);
  };

  return (
    <header>
      <div className='md:flex md:items-center md:justify-between mx-auto max-w-7xl mb-12'>
        <div className='min-w-0 flex-1'>
          <h1 className='text-3xl font-bold leading-tight tracking-tight text-gray-900'>
            Dashboard
          </h1>
        </div>
        <div className='mt-4 flex md:ml-4 md:mt-0'>
          <button
            type='button'
            id='scanImagesBtn'
            onClick={handleScanImagesClick}
            disabled={isScanning || isGenerating} // Disable if progress is ongoing
            className='inline-flex hidden items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
          >
            {isScanning ? 'Scanning...' : 'Scan Images'}
          </button>
          <button
            type='button'
            id='bulkGenerateBtn'
            onClick={fetchImages}
            disabled={
              imagesMissingAltText < 1 ||
              totalCreditsRemaining < 1 ||
              !licenseKey ||
              isGenerating ||
              isScanning
            } // Disable if progress is ongoing
            className='disabled:bg-gray-400 ml-3 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover-bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
          >
            {isGenerating ? 'Generating...' : 'Bulk Generate'}
          </button>
          {isGenerating && (
            <button
              type='button'
              onClick={handleCancelClick}
              className='ml-3 inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600'
            >
              Cancel
            </button>
          )}
        </div>
      </div>

      {(isGenerating || isScanning) && (
        <ProgressBar
          percentage={`${progress}%`}
          text={isGenerating ? 'Generating...' : 'Scanning...'} // Customize text prop
        />
      )}
    </header>
  );
}
