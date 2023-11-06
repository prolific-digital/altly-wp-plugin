import React, { useState, useEffect } from 'react';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/react/20/solid';
import Notification from './Notification';

// const people = [
//   {
//     name: 'Lindsay Walton',
//     title: 'Front-end Developer',
//     email: 'lindsay.walton@example.com',
//     role: 'Member',
//   },
// ];

function classNames(...classes) {
  return classes.filter(Boolean).join(' ');
}

export default function List() {
  const [images, setImages] = useState([]);
  const [currentPage, setCurrentPage] = useState(1); // Track the current page
  const [totalPages, setTotalPages] = useState(1); // Track the total number of pages
  const [showNotification, setShowNotification] = useState(false);
  const perPage = 5; // Define the perPage variable here

  // Get the base URL of the WordPress site
  const baseUrl = window.location.origin;

  const fetchImages = async (page) => {
    const mimeTypes = 'image/jpeg&image/png';

    try {
      const response = await fetch(
        `${baseUrl}/wp-json/wp/v2/media?page=${page}&per_page=${perPage}&mime_type=${mimeTypes}`
      );
      const data = await response.json();
      setImages(data);
      setCurrentPage(page); // Update the current page
      setTotalPages(response.headers.get('X-WP-TotalPages')); // Update the total number of pages
      console.log('imageList', data);
    } catch (error) {
      console.error('Error fetching media:', error);
    }
  };

  useEffect(() => {
    fetchImages(1); // Call the fetchImages function with page 1 when the component mounts
  }, []);

  const fetchImageCaptions = async () => {
    // Your license key
    const licenseKey = '1bb94cb8-695e-11ee-8c99-0242ac120002';

    const updatedImages = await Promise.all(
      images.map(async (image) => {
        const imageUrl = image.source_url;
        const analyzeResponse = await fetch(
          'https://api.altly.io/analyze/image',
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'license-key': licenseKey,
            },
            body: JSON.stringify({
              imageUrl: imageUrl,
            }),
          }
        );
        const analyzeData = await analyzeResponse.json();
        const caption = analyzeData.data[0].caption || '';
        console.log(caption);

        // Update the alt text right here
        updateAltText(image.id, caption);

        return {
          ...image,
          caption: caption,
        };
      })
    );

    // setImages(updatedImages) if needed
  };

  const updateAltText = async (imageId, altText) => {
    console.log(altText);
    try {
      const username = 'admin'; // Replace with your actual username
      const applicationPassword = '04Tk IPuH mv07 Yha6 nFBo lN1r'; // Replace with your actual application password

      const base64Credentials = btoa(`${username}:${applicationPassword}`);

      const updateResponse = await fetch(
        `${baseUrl}/wp-json/wp/v2/media/${imageId}`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            // Authorization: `Basic ${base64Credentials}`,
          },
          body: JSON.stringify({
            alt_text: altText,
          }),
        }
      );

      // Add these lines to log the full response if it's not ok
      if (!updateResponse.ok) {
        console.error(`Error updating alt text for image ${imageId}`);
        const responseData = await updateResponse.json();
        console.error('Response:', responseData);
      } else {
        console.log(`Alt text updated for image ${imageId}`);

        setImages((prevImages) => {
          return prevImages.map((image) => {
            if (image.id === imageId) {
              return {
                ...image,
                alt_text: altText,
              };
            }
            return image;
          });
        });
      }
    } catch (error) {
      console.error(`Error updating alt text for image ${imageId}`, error);
    }
  };

  const handleBulkGenerateClick = async () => {
    // Perform your bulk generate action here

    // Set the state to show the notification
    setShowNotification(true);

    // Fetch captions and update alt texts
    await fetchImageCaptions();

    // Optionally, you can add a delay and then hide the notification
    setTimeout(() => {
      setShowNotification(false);
    }, 10000); // Replace 3000 with the desired delay in milliseconds
  };

  return (
    <div className='px-4 sm:px-6 lg:px-8'>
      <div className='sm:flex sm:items-center'>
        <div className='sm:flex-auto'>
          {/* <h1 className='text-base font-semibold leading-6 text-gray-900'>
            Users
          </h1>
          <p className='mt-2 text-sm text-gray-700'>
            A list of all the users in your account including their name, title,
            email and role.
          </p> */}
        </div>
        <div className='mt-4 sm:ml-16 sm:mt-0 sm:flex-none'>
          <button
            type='button'
            className='block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
            onClick={handleBulkGenerateClick}
          >
            Bulk Generate Alt Text
          </button>
          <Notification showMessage={showNotification} />
        </div>
      </div>
      <div className='mt-8 flow-root'>
        <div className='-mx-4 -my-2 sm:-mx-6 lg:-mx-8'>
          <div className='inline-block min-w-full py-2 align-middle'>
            <table className='min-w-full border-separate border-spacing-0'>
              <thead>
                <tr>
                  <th
                    scope='col'
                    className='sticky top-0 z-10 border-b border-gray-300 bg-white bg-opacity-75 py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8'
                  >
                    Image
                  </th>
                  <th
                    scope='col'
                    className='sticky top-0 z-10 hidden border-b border-gray-300 bg-white bg-opacity-75 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:table-cell'
                  >
                    Alt Text
                  </th>
                  <th
                    scope='col'
                    className='sticky top-0 z-10 hidden border-b border-gray-300 bg-white bg-opacity-75 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter lg:table-cell'
                  >
                    ID
                  </th>
                  <th
                    scope='col'
                    className='sticky top-0 z-10 border-b border-gray-300 bg-white bg-opacity-75 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter'
                  >
                    Edit
                  </th>
                  <th
                    scope='col'
                    className='sticky top-0 z-10 border-b border-gray-300 bg-white bg-opacity-75 py-3.5 pl-3 pr-4 backdrop-blur backdrop-filter sm:pr-6 lg:pr-8'
                  >
                    <span className='sr-only'>Edit</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {images.map((image) => (
                  <tr key={image.id}>
                    <td
                      className={classNames(
                        'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8'
                      )}
                    >
                      <img
                        key={image.id}
                        src={image.source_url}
                        alt={image.alt_text}
                        width='100px'
                        height='100px'
                      />
                    </td>
                    <td
                      className={classNames(
                        'whitespace-nowrap hidden px-3 py-4 text-sm text-gray-500 sm:table-cell'
                      )}
                    >
                      {image.alt_text || 'No Alt Text'}
                    </td>

                    <td
                      className={classNames(
                        'whitespace-nowrap hidden px-3 py-4 text-sm text-gray-500 lg:table-cell'
                      )}
                    >
                      {image.id}
                    </td>
                    <td
                      className={classNames(
                        'whitespace-nowrap hidden px-3 py-4 text-sm text-gray-500 lg:table-cell'
                      )}
                    >
                      {/* http://plugin-tester.local/wp-admin/upload.php?item=10066 */}
                      <a
                        href={`${baseUrl}/wp/wp-admin/upload.php?item=${image.id}`}
                        className='text-indigo-600 hover:text-indigo-900'
                        target='_blank'
                        rel='noopener noreferrer'
                      >
                        Edit
                      </a>
                    </td>

                    <td
                    // className={classNames(
                    //   personIdx !== people.length - 1
                    //     ? 'border-b border-gray-200'
                    //     : '',
                    //   'whitespace-nowrap px-3 py-4 text-sm text-gray-500'
                    // )}
                    >
                      {/* {person.role} */}
                    </td>
                    <td
                    // className={classNames(
                    //   personIdx !== people.length - 1
                    //     ? 'border-b border-gray-200'
                    //     : '',
                    //   'relative whitespace-nowrap py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-8 lg:pr-8'
                    // )}
                    >
                      <a
                        href='#'
                        className='text-indigo-600 hover:text-indigo-900'
                      >
                        {/* Edit<span className='sr-only'>, {person.name}</span> */}
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div className='flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6'>
        <div className='flex flex-1 justify-between sm:hidden'>
          <a
            href='#'
            className='relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'
          >
            Previous
          </a>
          <a
            href='#'
            className='relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'
          >
            Next
          </a>
        </div>
        <div className='hidden sm:flex sm:flex-1 sm:items-center sm:justify-between'>
          <div>
            <p className='text-sm text-gray-700'>
              Showing{' '}
              <span className='font-medium'>
                {(currentPage - 1) * perPage + 1}
              </span>{' '}
              to{' '}
              <span className='font-medium'>
                {Math.min(currentPage * perPage, images.length)}
              </span>{' '}
              of <span className='font-medium'>{totalPages * perPage}</span>{' '}
              results
            </p>
          </div>
          <div>
            <nav
              className='isolate inline-flex -space-x-px rounded-md shadow-sm'
              aria-label='Pagination'
            >
              <a
                href='#'
                className='relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'
                onClick={() => {
                  if (currentPage > 1) {
                    fetchImages(currentPage - 1);
                  }
                }}
              >
                <span className='sr-only'>Previous</span>
                <ChevronLeftIcon className='h-5 w-5' aria-hidden='true' />
              </a>
              {/* Current: "z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600", Default: "text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-offset-0" */}
              {/* Display pagination links */}
              {Array.from({ length: totalPages }).map((_, index) => (
                <a
                  key={index}
                  href='#'
                  className={classNames(
                    'relative inline-flex items-center px-4 py-2 text-sm font-semibold',
                    index + 1 === currentPage
                      ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                      : 'text-gray-900 hover:bg-gray-50 focus:z-20 focus:outline-offset-0',
                    'ring-1 ring-inset ring-gray-300 focus:outline-offset-0'
                  )}
                  onClick={() => {
                    fetchImages(index + 1);
                    setCurrentPage(index + 1);
                  }}
                >
                  {index + 1}
                </a>
              ))}
              <a
                href='#'
                className='relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'
                onClick={() => {
                  if (currentPage < totalPages) {
                    fetchImages(currentPage + 1);
                  }
                }}
              >
                <span className='sr-only'>Next</span>
                <ChevronRightIcon className='h-5 w-5' aria-hidden='true' />
              </a>
            </nav>
          </div>
        </div>
      </div>
    </div>
  );
}
