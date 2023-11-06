import React, { useState, useEffect } from 'react';

const stats = [
  { name: 'Total Images', stat: 'Loading' },
  { name: 'Missing Alt Text', stat: 'Loading' },
];

export default function Stats() {
  const [images, setImages] = useState([]);

  // Get the base URL of the WordPress site
  const baseUrl = window.location.origin;

  const fetchImages = async () => {
    const perPage = 100; // Set the number of images per page
    const mimeTypes = 'image/jpeg&image/png';

    try {
      const response = await fetch(
        `${baseUrl}/wp-json/wp/v2/media?per_page=${perPage}&mime_type=${mimeTypes}`
      );
      const data = await response.json();
      const imageList = data; // Remove the media_type filter

      // Calculate total image count
      const totalImageCount = imageList.length;

      // Calculate missing alt text count
      let missingAltCount = 0;
      imageList.forEach((image) => {
        if (!image.alt_text) {
          missingAltCount++;
        }
      });

      // Update stats array with missing alt text count
      const updatedStats = [...stats];
      updatedStats[0].stat = totalImageCount.toString();
      updatedStats[1].stat = missingAltCount.toString(); // Convert to string

      setImages(imageList);
      // ...
    } catch (error) {
      console.error('Error fetching media:', error);
    }
  };

  useEffect(() => {
    fetchImages();
  }, []);

  return (
    <div>
      {/* <h3 className='text-base font-semibold leading-6 text-gray-900'>
        Last 30 days
      </h3> */}
      <dl className='mt-5 mb-5 grid grid-cols-1 gap-5 sm:grid-cols-3'>
        {stats.map((item) => (
          <div
            key={item.name}
            className='overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6'
          >
            <dt className='truncate text-sm font-medium text-gray-500'>
              {item.name}
            </dt>
            <dd className='mt-1 text-3xl font-semibold tracking-tight text-gray-900'>
              {item.stat}
            </dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
