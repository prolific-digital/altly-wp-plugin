import React, { useState } from 'react';

export default function Form({ onSubmit, children }) {
  // State to track the form input values
  const [formData, setFormData] = useState({});

  // Function to handle form submission
  const handleSubmit = (event) => {
    event.preventDefault();
    // Collect all form data including controlled inputs
    const allFormData = { ...formData };
    const formElements = event.target.elements;

    for (let i = 0; i < formElements.length; i++) {
      const element = formElements[i];
      if (element.name) {
        allFormData[element.name] = element.value;
      }
    }

    onSubmit(allFormData);
  };

  // Function to handle form input changes for uncontrolled inputs
  const handleInputChange = (event) => {
    const { name, value } = event.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  return (
    <form onSubmit={handleSubmit}>
      {React.Children.map(children, (child) => {
        // If it's not a valid element, return it as is
        if (!React.isValidElement(child)) {
          return child;
        }

        // If the child already has value and onChange props, respect those
        if (
          child.props.value !== undefined ||
          child.props.onChange !== undefined
        ) {
          return child;
        }

        // Only add form handling for inputs without existing value/onChange
        return React.cloneElement(child, {
          onChange: handleInputChange,
          value: formData[child.props.name] || '',
        });
      })}
    </form>
  );
}
