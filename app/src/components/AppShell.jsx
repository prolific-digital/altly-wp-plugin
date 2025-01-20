import { useState } from 'react';

import { Disclosure } from '@headlessui/react';
import {
  Bars3Icon,
  BellIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline';

import Link from './Link';

const user = {
  name: 'Tom Cook',
  email: 'tom@example.com',
  imageUrl:
    'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80',
};

function classNames(...classes) {
  return classes.filter(Boolean).join(' ');
}

export default function AppShell({ children }) {
  const currentUrl = window.location.href;
  const [navigation, setNavigation] = useState([
    {
      name: 'Dashboard',
      href: 'upload.php?page=altly&screen=dashboard',
      current: currentUrl.includes('dashboard'),
    },
    {
      name: 'Settings',
      href: 'upload.php?page=altly&screen=settings',
      current: currentUrl.includes('settings'),
    },
  ]);

  const handleSetActiveLink = (selectedHref) => {
    const updatedNavigation = navigation.map((item) => ({
      ...item,
      current: item.href === selectedHref,
    }));
    setNavigation(updatedNavigation);
  };

  return (
    <>
      {/*
        This example requires updating your template:

        ```
        <html class="h-full bg-gray-100">
        <body class="h-full">
        ```
      */}
      <div className='min-h-full'>
        <Disclosure as='nav' className='bg-white shadow-sm'>
          {({ open }) => (
            <>
              <div className='mx-auto max-w-7xl px-4 sm:px-6 lg:px-8'>
                <div className='flex h-16 justify-between'>
                  <div className='flex'>
                    <div className='flex flex-shrink-0 items-center'>
                      <img
                        className='block h-8 w-auto lg:hidden'
                        src='https://altly.ai/wp-content/uploads/2024/12/a-4.svg'
                        alt='Altly Logo'
                      />
                      <img
                        className='hidden h-8 w-auto lg:block'
                        src='https://altly.ai/wp-content/uploads/2024/12/a-4.svg'
                        alt='Altly Logo'
                      />
                    </div>
                    <div className='hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8'>
                      {navigation.map((item) => (
                        <Link
                          key={item.name}
                          href={item.href}
                          className={classNames(
                            item.current
                              ? 'border-indigo-500 text-gray-900'
                              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                            'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium'
                          )}
                          onClick={() => handleSetActiveLink(item.href)}
                          aria-current={item.current ? 'page' : undefined}
                        >
                          {item.name}
                        </Link>
                      ))}
                    </div>
                  </div>
                  <div className='hidden sm:ml-6 sm:flex sm:items-center'>
                    <button
                      type='button'
                      className='relative rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2'
                    >
                      <span className='absolute -inset-1.5' />
                      <span className='sr-only'>View notifications</span>
                      <BellIcon className='h-6 w-6' aria-hidden='true' />
                    </button>
                  </div>
                  <div className='-mr-2 flex items-center sm:hidden'>
                    {/* Mobile menu button */}
                    <Disclosure.Button className='relative inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2'>
                      <span className='absolute -inset-0.5' />
                      <span className='sr-only'>Open main menu</span>
                      {open ? (
                        <XMarkIcon
                          className='block h-6 w-6'
                          aria-hidden='true'
                        />
                      ) : (
                        <Bars3Icon
                          className='block h-6 w-6'
                          aria-hidden='true'
                        />
                      )}
                    </Disclosure.Button>
                  </div>
                </div>
              </div>

              <Disclosure.Panel className='sm:hidden'>
                <div className='space-y-1 pb-3 pt-2'>
                  {navigation.map((item) => (
                    <Disclosure.Button
                      key={item.name}
                      as='a'
                      href={item.href}
                      className={classNames(
                        item.current
                          ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                          : 'border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800',
                        'block border-l-4 py-2 pl-3 pr-4 text-base font-medium'
                      )}
                      aria-current={item.current ? 'page' : undefined}
                    >
                      {item.name}
                    </Disclosure.Button>
                  ))}
                </div>
              </Disclosure.Panel>
            </>
          )}
        </Disclosure>

        <div className='py-10'>
          <main>
            <div className='mx-auto max-w-7xl sm:px-6 lg:px-8'>{children}</div>
          </main>
        </div>
      </div>
    </>
  );
}
