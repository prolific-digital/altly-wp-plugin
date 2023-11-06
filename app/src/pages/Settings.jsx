import React, { useState } from 'react';
import Heading from '../components/Heading';
import Input from '../components/Input';
import Form from '../components/Form';

export default function Example() {
  // State to track the input value
  const [inputValue, setInputValue] = useState('');

  // State to track whether the value is correct
  const [isValueCorrect, setIsValueCorrect] = useState(false);

  // State to track whether there's an error
  const [isError, setIsError] = useState(false);

  // Function to handle input change
  const handleInputChange = (event) => {
    setInputValue(event.target.value);
    setIsError(false); // Reset error state when input changes
  };

  // This requires additional work to manage state.

  // Function to handle save button click
  const handleSaveClick = (formData) => {
    const licenseKey = formData['license-key']; // Access the value by using the input field's name as the key

    setInputValue(licenseKey);

    // You can add your validation logic here
    const isValid = validateInput(licenseKey);

    // Update the state based on validation result
    setIsValueCorrect(isValid);

    // Set isError to true if validation fails
    setIsError(!isValid);
  };

  // Function to validate the input (replace with your validation logic)
  const validateInput = (value) => {
    // Replace this with your validation logic
    return value === 'correctValue';
  };

  return (
    <div>
      <Heading text='Settings' />
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
          Save
        </button>
      </Form>
    </div>
  );
}
