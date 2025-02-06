// https://ncoughlin.com/posts/react-navigation-without-react-router/
import React from 'react';

const Link = ({ className, href, children, onClick }) => {
  // prevent full page reload
  const handleClick = (event) => {
    // if ctrl or meta key are held on click, allow default behavior of opening link in new tab
    if (event.metaKey || event.ctrlKey) {
      return;
    }

    event.preventDefault();
    window.history.pushState({}, '', href);

    // communicate to Routes that URL has changed
    const navEvent = new PopStateEvent('popstate');
    window.dispatchEvent(navEvent);

    // Call the provided onClick function
    if (onClick) {
      onClick(event);
    }
  };

  return (
    <a className={className} href={href} onClick={handleClick}>
      {children}
    </a>
  );
};

export default Link;
