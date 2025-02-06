import { useState, useEffect } from 'react';
import './App.scss';
import AppShell from './components/AppShell';
import Route from './components/Route';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';

function App() {
  useEffect(() => {
    const searchParams = new URLSearchParams(window.location.search);
    const page = searchParams.get('page');
    const screen = searchParams.get('screen');

    if (page === 'altly' && !screen) {
      window.location.search = searchParams.toString() + '&screen=dashboard';
    }
  }, []);
  
  return (
    <>
      <AppShell>
        <Route path='/wp/wp-admin/upload.php' params={{ screen: 'dashboard' }}>
          <Dashboard />
        </Route>
        <Route path='/wp/wp-admin/upload.php' params={{ screen: 'settings' }}>
          <Settings />
        </Route>
        <Route path='/wp-admin/upload.php' params={{ screen: 'dashboard' }}>
          <Dashboard />
        </Route>
        <Route path='/wp-admin/upload.php' params={{ screen: 'settings' }}>
          <Settings />
        </Route>
      </AppShell>
    </>
  );
}

export default App;
