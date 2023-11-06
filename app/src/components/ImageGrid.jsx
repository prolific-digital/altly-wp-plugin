import React, { useState, useEffect } from 'react';
import ImageGridLoader from '../components/ImageGridLoader';
import Stats from '../components/Stats';
import Pagination from '../components/Pagination';

export default function Example() {
  const [files, setFiles] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [averageConfidenceScore, setAverageConfidenceScore] = useState(null);

  /*
    Update the URL to the CMS API endpoint.
  */
  const cmsImageApiUrl = 'https://picsum.photos/v2/list?page=99&limit=10';

  useEffect(() => {
    const fetchData = async (pageUrl) => {
      try {
        const response = await fetch(pageUrl);
        const data = await response.json();

        /*
          
        */

        // Process the data and create new file objects
        const newFiles = data.map((item) => ({
          id: item.id,
          title: `Image ${item.id}`,
          size: `${item.width}x${item.height}`,
          altText: 'No alt text',
          confidenceScore: 90,
          source: item.download_url,
        }));
        setFiles(newFiles);

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

  return (
    <div>
      {/*
        Stat data should be supplied by both the Altly API and CMS API.

        totalImages: CMS API - Total number of images in the CMS
        missingAltText: CMS API - Total number of images missing alt text
        score:  Altly API - Average confidence score of all images in the CMS
        credits: Altly API - Number of credits remaining in the Altly account
      */}
      <Stats
        totalImages='999'
        missingAltText='999'
        score={averageConfidenceScore}
        credits='1000'
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
                  {file.altText}
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
