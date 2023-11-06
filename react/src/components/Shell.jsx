import { Fragment } from 'react';
import { Disclosure, Menu, Transition } from '@headlessui/react';
import { Bars3Icon, BellIcon, XMarkIcon } from '@heroicons/react/24/outline';

const user = {
  name: 'Tom Cook',
  email: 'tom@example.com',
  imageUrl:
    'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80',
};
const navigation = [
  { name: 'Dashboard', href: '#', current: true },
  { name: 'Settings', href: '#', current: false },
  // { name: 'Projects', href: '#', current: false },
  // { name: 'Calendar', href: '#', current: false },
];
const userNavigation = [
  { name: 'Your Profile', href: '#' },
  { name: 'Settings', href: '#' },
  { name: 'Sign out', href: '#' },
];

function classNames(...classes) {
  return classes.filter(Boolean).join(' ');
}

export default function Shell({ children }) {
  return (
    <>
      {/*
        This example requires updating your template:

        ```
        <html class="h-full bg-gray-100">
        <body class="h-full">
        ```
      */}
      <div className='h-1/6'>
        <Disclosure as='nav' className='bg-white shadow-sm'>
          {({ open }) => (
            <>
              <div className='mx-auto max-w-7xl px-4 sm:px-6 lg:px-8'>
                <div className='flex h-16 justify-between'>
                  <div className='flex'>
                    <div className='flex flex-shrink-0 items-center'>
                      <div className='flex flex-shrink-0 items-center h-8 w-auto'>
                        <svg
                          xmlns='http://www.w3.org/2000/svg'
                          width='50'
                          viewBox='0 0 725 370'
                          fill='none'
                        >
                          <path
                            d='M138.381 265.378C127.384 281.68 111.463 290.024 90.6099 290.024C38.2941 290.024 0 250.597 0 192.217C0 133.836 38.6669 94.7821 91.7426 94.7821H199.034V284.339H138.374V265.386L138.381 265.378ZM100.846 238.841C123.972 238.841 138.381 219.887 138.381 191.449V144.058H100.846C78.1007 144.058 62.5593 163.392 62.5593 191.829C62.5593 220.267 78.1007 238.841 100.846 238.841Z'
                            fill='#0098FF'
                          />
                          <path
                            d='M226.711 0H287.371V284.332H279.787C247.944 284.332 226.711 263.099 226.711 231.256V0Z'
                            fill='#0098FF'
                          />
                          <path
                            d='M403.752 284.332C343.092 284.332 311.25 254.002 311.25 192.589V37.9143H371.909V94.7821H426.498V144.066H371.909V192.589C371.909 210.783 381.766 235.048 406.412 235.048H428.777V284.332H403.759H403.752Z'
                            fill='#0098FF'
                          />
                          <path
                            d='M448.856 0H509.516V284.332H501.932C470.09 284.332 448.856 263.099 448.856 231.256V0Z'
                            fill='#0098FF'
                          />
                          <path
                            d='M724.851 94.7749V268.783C724.851 329.063 698.313 369.63 624.005 369.63C591.402 369.63 560.692 359.393 533.394 328.683L570.929 295.321C588.75 311.242 602.011 318.447 624.384 318.447C653.575 318.447 661.919 303.285 663.811 280.54H628.929C556.52 280.54 537.186 236.181 537.186 193.342V94.7749H597.846V192.962C597.846 215.708 613.388 231.249 638.033 231.249H664.191V94.7677H724.851V94.7749Z'
                            fill='#0098FF'
                          />
                        </svg>
                      </div>
                      {/* <img
                        className='block h-8 w-auto'
                        src='https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600'
                        alt='Alty'
                      /> */}
                      {/* <img
                        className='hidden h-8 w-auto lg:block'
                        src='https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600'
                        alt='Alty'
                      /> */}
                    </div>
                    <div className='hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8'>
                      {navigation.map((item) => (
                        <a
                          key={item.name}
                          href={item.href}
                          className={classNames(
                            item.current
                              ? 'border-indigo-500 text-gray-900'
                              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                            'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium'
                          )}
                          aria-current={item.current ? 'page' : undefined}
                        >
                          {item.name}
                        </a>
                      ))}
                    </div>
                  </div>
                  <div className='hidden sm:ml-6 sm:flex sm:items-center'>
                    {/* <button
                      type='button'
                      className='relative rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2'
                    >
                      <span className='absolute -inset-1.5' />
                      <span className='sr-only'>View notifications</span>
                      <BellIcon className='h-6 w-6' aria-hidden='true' />
                    </button> */}

                    {/* Profile dropdown */}
                    {/* <Menu as='div' className='relative ml-3'>
                      <div>
                        <Menu.Button className='relative flex rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2'>
                          <span className='absolute -inset-1.5' />
                          <span className='sr-only'>Open user menu</span>
                          <img
                            className='h-8 w-8 rounded-full'
                            src={user.imageUrl}
                            alt=''
                          />
                        </Menu.Button>
                      </div>
                      <Transition
                        as={Fragment}
                        enter='transition ease-out duration-200'
                        enterFrom='transform opacity-0 scale-95'
                        enterTo='transform opacity-100 scale-100'
                        leave='transition ease-in duration-75'
                        leaveFrom='transform opacity-100 scale-100'
                        leaveTo='transform opacity-0 scale-95'
                      >
                        <Menu.Items className='absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none'>
                          {userNavigation.map((item) => (
                            <Menu.Item key={item.name}>
                              {({ active }) => (
                                <a
                                  href={item.href}
                                  className={classNames(
                                    active ? 'bg-gray-100' : '',
                                    'block px-4 py-2 text-sm text-gray-700'
                                  )}
                                >
                                  {item.name}
                                </a>
                              )}
                            </Menu.Item>
                          ))}
                        </Menu.Items>
                      </Transition>
                    </Menu> */}
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
                <div className='border-t border-gray-200 pb-3 pt-4'>
                  <div className='flex items-center px-4'>
                    <div className='flex-shrink-0'>
                      <img
                        className='h-10 w-10 rounded-full'
                        src={user.imageUrl}
                        alt=''
                      />
                    </div>
                    <div className='ml-3'>
                      <div className='text-base font-medium text-gray-800'>
                        {user.name}
                      </div>
                      <div className='text-sm font-medium text-gray-500'>
                        {user.email}
                      </div>
                    </div>
                    <button
                      type='button'
                      className='relative ml-auto flex-shrink-0 rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2'
                    >
                      <span className='absolute -inset-1.5' />
                      <span className='sr-only'>View notifications</span>
                      <BellIcon className='h-6 w-6' aria-hidden='true' />
                    </button>
                  </div>
                  <div className='mt-3 space-y-1'>
                    {userNavigation.map((item) => (
                      <Disclosure.Button
                        key={item.name}
                        as='a'
                        href={item.href}
                        className='block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800'
                      >
                        {item.name}
                      </Disclosure.Button>
                    ))}
                  </div>
                </div>
              </Disclosure.Panel>
            </>
          )}
        </Disclosure>

        <div className='py-10'>
          <header>
            <div className='mx-auto max-w-7xl px-4 sm:px-6 lg:px-8'>
              <h1 className='text-3xl font-bold leading-tight tracking-tight text-gray-900'>
                Dashboard
              </h1>
            </div>
          </header>
          <main>
            <div className='mx-auto max-w-7xl sm:px-6 lg:px-8'>{children}</div>
          </main>
        </div>
      </div>
    </>
  );
}
