import React, { useState, useEffect } from 'react';
import ProgressBar from '../components/ProgressBar';

export default function HeadingDashboard({ data }) {
  const [isGenerating, setIsGenerating] = useState(false);
  const [isScanning, setIsScanning] = useState(false);
  const [progress, setProgress] = useState(0);

  // console.log(data);

  // Use useEffect to watch for changes in isGenerating and isScanning
  useEffect(() => {
    if (isGenerating || isScanning) {
      // Disable buttons when progress is ongoing
      document.getElementById('bulkGenerateBtn').disabled = true;
      document.getElementById('scanImagesBtn').disabled = true;
    } else {
      // Enable buttons when progress is not ongoing
      document.getElementById('bulkGenerateBtn').disabled = false;
      document.getElementById('scanImagesBtn').disabled = false;
    }
  }, [isGenerating, isScanning]);

  const handleBulkGenerateClick = async () => {
    if (!isGenerating) {
      setIsGenerating(true);
      setProgress(0); // Reset progress when Bulk Generate is clicked

      const totalSteps = 100; // You can adjust this based on your desired granularity
      const timeInterval = 2000 / totalSteps;

      const intervalId = setInterval(() => {
        setProgress((prevProgress) => {
          const newProgress = prevProgress + 1;
          if (newProgress === totalSteps) {
            clearInterval(intervalId);
            setIsGenerating(false);
          }
          return newProgress;
        });
      }, timeInterval);
    }

    try {
      // Make a POST request to the API endpoint
      const response = await fetch(
        'https://altlyplugin.prolificdigital.io/wp-json/altly/v1/bulk-generate',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ image_data: data }), // Send the license key as JSON
        }
      );

      const res = await response.json();

      console.log(res);

      // if (response.ok) {
      //   setIsValueCorrect(true);
      //   setSuccessMessage(data.message);
      //   // loadLicenseKey();
      //   // console.log(data.message);
      // } else {
      //   setIsValueCorrect(false);
      //   setIsError(true);
      //   setSuccessMessage(data.message); // Display the error message from the server

      //   // console.log(data.message);
      // }
    } catch (error) {
      // console.error('Error while making API call:', error);
      // setIsValueCorrect(false);
      // setIsError(true);
      // setSuccessMessage('An error occurred while validating the license key.');
    }
  };

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
            className='inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
          >
            {isScanning ? 'Scanning...' : 'Scan Images'}
          </button>
          <button
            type='button'
            id='bulkGenerateBtn'
            onClick={handleBulkGenerateClick}
            disabled={isGenerating || isScanning} // Disable if progress is ongoing
            className='ml-3 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover-bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
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
