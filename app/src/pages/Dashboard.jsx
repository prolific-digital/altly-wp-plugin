import React, { useState } from 'react';
import ImageGrid from '../components/ImageGrid';
import HeadingDashboard from '../components/HeadingDashboard';

export default function Dashboard() {
  const [imageData, setImageData] = useState(null);

  // Function to update the state, which will be passed to ImageGrid
  const handleDataChange = newData => {
    setImageData(newData);
  };

  return (
    <>
      <HeadingDashboard data={imageData} />
      <ImageGrid onDataChange={handleDataChange} />
    </>
  );
}
