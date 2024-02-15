import React, { useState } from 'react';
import ImageGrid from '../components/ImageGrid';
import HeadingDashboard from '../components/HeadingDashboard';
import getBaseUrl from '../helpers/baseUrlHelper';

export default function Dashboard() {
  const [imageData, setImageData] = useState(null);
  
  // console.log(getBaseUrl());


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
