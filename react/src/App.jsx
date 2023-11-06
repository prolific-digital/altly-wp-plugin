import { useState, Fragment } from 'react';
import reactLogo from './assets/react.svg';
import viteLogo from '/vite.svg';
import './App.css';
import Shell from './components/Shell';
import List from './components/List';
import Stats from './components/Stats';
import Demo from './components/demo';

export function App() {
  return (
    <Fragment>
      <Shell>
        <Stats />
        <List />
      </Shell>
    </Fragment>
  );
}
