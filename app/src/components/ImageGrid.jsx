import React, { useState, useEffect } from 'react';
import ImageGridLoader from '../components/ImageGridLoader';
import Stats from '../components/Stats';
import Pagination from '../components/Pagination';
import StatsLoader from '../components/StatsLoader';
import getBaseUrl from '../helpers/baseUrlHelper';

export default function ImageGrid({ onDataChange }) {
  const [files, setFiles] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [statsLoading, setstatsLoading] = useState(true);
  const [averageConfidenceScore, setAverageConfidenceScore] = useState(null);
  const [totalImages, setTotalImages] = useState(0);
  const [totalCreditsRemaining, setTotalCreditsRemaining] = useState(0);
  const [imagesMissingAltText, setImagesMissingAltText] = useState(0);
  const [altImageData, setAltImageData] = useState();

  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 20; // Number of items to show per page

  const updateData = () => {
    onDataChange(altImageData); // Update the parent component's state
  };

  useEffect(() => {
    if (altImageData) {
      updateData();
    }
  }, [altImageData]);

  // Calculate which files to display based on the current page
  const indexOfLastFile = currentPage * itemsPerPage;
  const indexOfFirstFile = indexOfLastFile - itemsPerPage;
  const currentFiles = files.slice(indexOfFirstFile, indexOfLastFile);

  // Pagination handlers
  const handlePreviousPage = () => {
    setCurrentPage((prevPage) => (prevPage > 1 ? prevPage - 1 : prevPage));
  };

  const handleNextPage = () => {
    setCurrentPage((prevPage) => (prevPage < Math.ceil(files.length / itemsPerPage) ? prevPage + 1 : prevPage));
  };

  /*
    Update the URL to the CMS API endpoint.
  */
  const cmsImageApiUrl =
    getBaseUrl()+'/wp-json/altly/v1/get-media-details';

  useEffect(() => {
    const fetchData = async (pageUrl) => {
      try {
        const response = await fetch(pageUrl);
        const data = await response.json();

        const itemsWithMissingAltText = data.media_details
        .filter(item => !item.alt_text || item.alt_text.trim() === '') // Filter items with missing or empty alt_text
        .map(item => ({
          // Map each filtered item to a new object structure
          alt_text: item.alt_text,
          file_path: item.file_path,
          id: item.id,
          metadata: item.metadata,
          url: item.url
        }));

        console.log('All Data:', data);
        // console.log('Missing Alt Text:', itemsWithMissingAltText);
        // we're setting this below so we can send it to the dashboard which 
        // connects and sends it to the HeadingDashboard for bulk generation
        setAltImageData(itemsWithMissingAltText);

        // Process the data and create new file objects, these are the images displayed on the plugin dashboard
        const newFiles = data.media_details.filter(item => !item.alt_text || item.alt_text.trim() === '').map((item) => ({
          id: item.id,
          title: `Image ${item.id}`,
          size: `${item.metadata.width}x${item.metadata.height}`,
          altText: item.alt_text,
          confidenceScore: Math.round(item.confidence_score * 100) / 100,
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

  // const validateLicenseUrl = 'http://localhost:3000/validate/license-key';

  useEffect(() => {
    const getUserCredits = async () => {
      const url = getBaseUrl()+'/wp-json/altly/v1/get-user-credits';
      try {
        const response = await fetch(url);

        if (!response.ok) {
          throw new Error(`Error: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();
        
        if (data && data.credits !== undefined) {
          // setUserData({ credits: data.credits });
          setTotalCreditsRemaining(data.credits);
          setstatsLoading(false);
        } else {
          setstatsLoading(false);
        }

        // console.log(data);

      } catch (error) {
        setstatsLoading(false);
        console.error('Error fetching user credits:', error);
      }
    };

    getUserCredits();
  }, []);

  return (
    <div>
      {statsLoading ? <StatsLoader /> : 
      <Stats
        totalImages={totalImages}
        missingAltText={imagesMissingAltText}
        score={averageConfidenceScore}
        credits={totalCreditsRemaining}
      />}
      {isLoading ? (
        <ImageGridLoader />
      ) : (
        <div>
          <ul
            role='list'
            className='grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8'
          >
            {currentFiles.map((file, index) => (
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
                {/* <p className='pointer-events-none mt-2 block truncate text-sm font-medium text-gray-900'>
                  {file.confidenceScore}
                </p> */}
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
          <Pagination
            currentPage={currentPage}
            itemsPerPage={itemsPerPage}
            totalItems={files.length}
            handlePreviousPage={handlePreviousPage}
            handleNextPage={handleNextPage}
          />
        </div>
      )}
    </div>
  );
}




