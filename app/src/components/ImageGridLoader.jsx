const files = [
  { title: 'Placeholder' },
  { title: 'Placeholder' },
  { title: 'Placeholder' },
  { title: 'Placeholder' },
  { title: 'Placeholder' },
  { title: 'Placeholder' },
  { title: 'Placeholder' },
  { title: 'Placeholder' },
];

export default function Example() {
  return (
    <ul
      role='list'
      className='grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8'
    >
      {files.map((file, index) => (
        <li key={index} className='relative'>
          <div className='animate-pulse group aspect-h-7 aspect-w-10 block w-full overflow-hidden rounded-lg bg-gray-400 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100'>
            {/* <img
              src={file.source}
              alt=''
              className='pointer-events-none object-cover group-hover:opacity-75'
            />
            <button
              type='button'
              className='absolute inset-0 focus:outline-none'
            >
              <span className='sr-only'>View details for {file.title}</span>
            </button> */}
          </div>
          {/* <p className='pointer-events-none mt-2 block truncate text-sm font-medium text-gray-900'>
            {file.title}
          </p>
          <p className='pointer-events-none block text-sm font-medium text-gray-500'>
            {file.size}
          </p> */}
        </li>
      ))}
    </ul>
  );
}
