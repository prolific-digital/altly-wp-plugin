import { useState } from 'react';
import './App.scss';
import AppShell from './components/AppShell';
import Route from './components/Route';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';

function App() {
  return (
    <>
      <AppShell>
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
