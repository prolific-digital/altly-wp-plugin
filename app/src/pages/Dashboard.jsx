import { useEffect, useState } from 'react';

import HeadingDashboard from '../components/HeadingDashboard';
import ImageGrid from '../components/ImageGrid';
import getBaseUrl from '../helpers/baseUrlHelper';

export default function Dashboard() {
  const [imageData, setImageData] = useState(null);
  const [totalCreditsRemaining, setTotalCreditsRemaining] = useState(0);

  // Function to update the state, which will be passed to ImageGrid
  const handleDataChange = (newData) => {
    setImageData(newData);
  };

  useEffect(() => {
    const getUserCredits = async () => {
      const url = getBaseUrl() + '/wp-json/altly/v1/get-user-credits';
      try {
        const response = await fetch(url);

        if (!response.ok) {
          throw new Error(`Error: ${response.status} ${response.statusText}`);
        }

        const credits = await response.json();

        if (credits !== false) {
          // setUserData({ credits: data.credits });
          setTotalCreditsRemaining(credits);
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
    <>
      <HeadingDashboard
        data={imageData}
        totalCreditsRemaining={totalCreditsRemaining}
      />
      <ImageGrid
        onDataChange={handleDataChange}
        totalCreditsRemaining={totalCreditsRemaining}
      />
    </>
  );
}
