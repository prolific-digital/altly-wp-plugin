import React, { useState } from 'react';

export default function Form({ onSubmit, children }) {
  // State to track the form input values
  const [formData, setFormData] = useState({});

  // Function to handle form input changes
  const handleInputChange = (event) => {
    const { name, value } = event.target;
    setFormData({ ...formData, [name]: value });
  };

  // Function to handle form submission
  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit(formData);
  };

  // Function to get the current form data
  const getFormData = () => {
    return formData;
  };

  return (
    <form onSubmit={handleSubmit}>
      {React.Children.map(children, (child) =>
        React.cloneElement(child, {
          onChange: handleInputChange,
          value: formData[child.props.name] || '', // Pass the value from formData
        })
      )}
    </form>
  );
}
