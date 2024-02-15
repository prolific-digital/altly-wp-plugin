import React, { useState, useEffect } from 'react';
import Heading from '../components/Heading';
import Input from '../components/Input';
import Form from '../components/Form';
import InputLoader from '../components/InputLoader';
import getBaseUrl from '../helpers/baseUrlHelper';

export default function Example() {
  // State to track the input value
  const [inputValue, setInputValue] = useState('');

  // State to track whether the value is correct
  const [isValueCorrect, setIsValueCorrect] = useState(false);

  // State to track whether there's an error
  const [isError, setIsError] = useState(false);
  
  // Track load state
  const [isLoading, setIsLoading] = useState(true);

  const [successMessage, setSuccessMessage] = useState('');

  // Function to handle input change
  const handleInputChange = (event) => {
    setInputValue(event.target.value);
    setIsError(false); // Reset error state when input changes
  };

  const handleSaveClick = async (formData) => {
    const licenseKey = formData['license-key'];

    setInputValue(licenseKey);

    try {
      // Make a POST request to the API endpoint
      const response = await fetch(
        getBaseUrl()+'/wp-json/altly/v1/license-key',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ license_key: licenseKey }), // Send the license key as JSON
        }
      );

      const data = await response.json();

      console.log(data);

      if (response.ok) {
        setIsValueCorrect(true);
        setIsError(false);
        setSuccessMessage(data.message);
        loadLicenseKey();
        // console.log(data.message);
      } else {
        setIsValueCorrect(false);
        setIsError(true);
        setSuccessMessage(data.message); // Display the error message from the server
        // loadLicenseKey();
        setInputValue('');

        // console.log(data.message);
      }
    } catch (error) {
      console.error('Error while making API call:', error);
      setIsValueCorrect(false);
      setIsError(true);
      setSuccessMessage('An error occurred while validating the license key.');
    }
  };

  const handleLicenseRemoval = async () => {
    try {
      const response = await fetch(
        getBaseUrl()+'/wp-json/altly/v1/remove-license-key'
      );
      // const data = await response.json();

      if (response.ok) {
        setIsLoading(false);
        loadLicenseKey();
      } else {
        setIsLoading(false);
        loadLicenseKey();
        throw new Error('License key not found');
      }
    } catch (error) {
      console.error('Error while loading the license key:', error);
      setIsError(true);
      setIsLoading(false);
      setSuccessMessage(
        error.message || 'An error occurred while loading the license key.'
      );
    }
  }

  // Function to validate the input (replace with your validation logic)
  const validateInput = (value) => {
    // Replace this with your validation logic
    return value === 'correctValue';
  };

  // Function to load the license key from the API
  

  const loadLicenseKey = async () => {
    try {
      const response = await fetch(
        getBaseUrl()+'/wp-json/altly/v1/license-key'
      );
      const data = await response.json();

      if (response.ok && data.license_key) {
        setInputValue(data.license_key); // Set the license key if it exists
        setIsValueCorrect(true); // Assuming the key is correct if it's present
        setIsLoading(false);
      } else {
        setInputValue('');
        setIsLoading(false);
        throw new Error('License key not found');
      }
    } catch (error) {
      console.error('Error while loading the license key:', error);
      setIsError(true);
      setIsLoading(false);
      setSuccessMessage(
        error.message || 'An error occurred while loading the license key.'
      );
    }
  };

  // Effect to run once on component mount to load the license key
  useEffect(() => {
    loadLicenseKey();
  }, []); // The empty array ensures this effect runs only once

  return (
    <div>
      <Heading text='Settings' />
      {isLoading ? (
        <InputLoader /> // Show a loading indicator
      ) : inputValue.length > 0 && isValueCorrect ? (
        <>
          <Input
            label='License Key'
            type='text'
            placeholder='xxx-xxx-xxx'
            name='license-key'
            value={inputValue} // Pass the input value
            onChange={handleInputChange} // Pass the onChange handler
            isError={isError}
            isValueCorrect={isValueCorrect}
            successMessage='License key is valid'
            disabled={true}
          />
          <button className='rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' onClick={handleLicenseRemoval}>Remove License</button>
        </>
      ) : (
        <Form onSubmit={handleSaveClick}>
          <Input
            label='License Key'
            type='text'
            placeholder='xxx-xxx-xxx'
            name='license-key'
            value={inputValue} // Pass the input value
            onChange={handleInputChange} // Pass the onChange handler
            isError={isError}
            isValueCorrect={isValueCorrect}
            successMessage='License key is valid'
          />
          <button
            type='submit'
            className='rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
          >
            Save License
          </button>
        </Form>
      )}
    </div>
  );
}
