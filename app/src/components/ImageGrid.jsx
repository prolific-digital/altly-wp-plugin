import React, { useState, useEffect } from 'react';
import ImageGridLoader from '../components/ImageGridLoader';
import Stats from '../components/Stats';
import Pagination from '../components/Pagination';

export default function Example() {
  const [files, setFiles] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [averageConfidenceScore, setAverageConfidenceScore] = useState(null);
  const [totalImages, setTotalImages] = useState(0);
  const [imagesMissingAltText, setImagesMissingAltText] = useState(0);
  const [userData, setUserData] = useState();

  /*
    Update the URL to the CMS API endpoint.
  */
  const cmsImageApiUrl =
    'http://plugin-tester.local/wp-json/altly/v1/get-media-details';

  useEffect(() => {
    const fetchData = async (pageUrl) => {
      try {
        const response = await fetch(pageUrl);
        const data = await response.json();

        console.log('Data:', data);

        // Process the data and create new file objects
        const newFiles = data.media_details.map((item) => ({
          id: item.id,
          title: `Image ${item.id}`,
          size: `${item.metadata.width}x${item.metadata.height}`,
          altText: item.alt_text,
          confidenceScore: 90,
          source: item.url,
        }));
        setFiles(newFiles);
        setTotalImages(data.total_images);
        setImagesMissingAltText(data.images_missing_alt_text);

        // Calculate the average confidence score
        const totalConfidenceScore = newFiles.reduce(
          (sum, file) => sum + file.confidenceScore,
          0
        );
        const averageScore =
          newFiles.length > 0
            ? Math.floor(totalConfidenceScore / newFiles.length) + '%'
            : '0%';
        setAverageConfidenceScore(averageScore);

        setTimeout(() => {
          setIsLoading(false);
        }, 1000);
      } catch (error) {
        console.error('Error fetching data:', error);
        setIsLoading(false);
      }
    };

    fetchData(cmsImageApiUrl);
  }, []);

  const valideLicenseUrl = 'http://localhost:3000/validate/license-key';

  useEffect(() => {
    const postData = async (pageUrl) => {
      try {
        // Create headers object with the license-key header
        const headers = new Headers();
        headers.append('license-key', '1f446e9e-f40f-4097-acff-bc6fec8be655'); // Replace 'YOUR_LICENSE_KEY_HERE' with the actual license key

        // Make the fetch request with the headers and POST method
        const response = await fetch(pageUrl, {
          method: 'POST',
          headers: headers,
        });

        // Check if the response status is OK (200)
        if (response.ok) {
          const responseData = await response.json();
          setUserData(responseData.data);
        } else {
          console.error('Error:', response.status, response.statusText);
        }
      } catch (error) {
        console.error('Error fetching data:', error);
      }
    };

    postData(valideLicenseUrl);
  }, []);

  return (
    <div>
      <Stats
        totalImages={totalImages}
        missingAltText={imagesMissingAltText}
        score={averageConfidenceScore}
        credits={userData ? userData.credits : 0}
      />
      {isLoading ? (
        <ImageGridLoader />
      ) : (
        <div>
          <ul
            role='list'
            className='grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8'
          >
            {files.map((file, index) => (
              <li key={file.id} className='relative'>
                <div className='group aspect-h-7 aspect-w-10 block w-full overflow-hidden rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100'>
                  <img
                    src={file.source}
                    alt=''
                    className='pointer-events-none object-cover group-hover:opacity-75'
                  />
                  <button
                    type='button'
                    className='absolute inset-0 focus:outline-none'
                  >
                    <span className='sr-only'>
                      View details for {file.title}
                    </span>
                  </button>
                </div>
                <p className='pointer-events-none mt-2 block truncate text-sm font-medium text-gray-900'>
                  {file.confidenceScore}
                </p>
                <p className='pointer-events-none block truncate text-sm font-medium text-gray-900'>
                  {file.altText ? file.altText : 'Missing Alt Text'}
                </p>
                <p className='pointer-events-none block truncate text-sm font-medium text-gray-900'>
                  {file.title}
                </p>
                <p className='pointer-events-none block text-sm font-medium text-gray-500'>
                  {file.size}
                </p>
              </li>
            ))}
          </ul>

          {/*
            Pagination should be supplied by the CMS API and may differ based on the CMS.
          */}
          <Pagination />
        </div>
      )}
    </div>
  );
}
