import { useEffect, useState } from 'react';

const Route = ({ path, params, children }) => {
  // state to track URL and force component to re-render on change
  const [currentPath, setCurrentPath] = useState(window.location.pathname);
  const [currentSearchParams, setCurrentSearchParams] = useState(
    new URLSearchParams(window.location.search)
  );

  useEffect(() => {
    // define callback as a separate function so it can be removed later with cleanup function
    const onLocationChange = () => {
      // update path state to current window URL
      setCurrentPath(window.location.pathname);
      // update search params state to current window URL search params
      setCurrentSearchParams(new URLSearchParams(window.location.search));
    };

    window.addEventListener('popstate', onLocationChange);

    // clean up event listener
    return () => {
      window.removeEventListener('popstate', onLocationChange);
    };
  }, []);

  // Check if the current path matches the provided path
  const pathMatches = currentPath === path;

  // Check if there are parameters and if they match
  let paramsMatch = true;
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (currentSearchParams.get(key) !== value) {
        paramsMatch = false;
        break;
      }
    }
  }

  return pathMatches && paramsMatch ? children : null;
};

export default Route;
