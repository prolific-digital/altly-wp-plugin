const files = [
    { title: 'Placeholder' },
  ];
  
  export default function InputLoader() {
    return (
      <ul
        role='list'
        className='grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8'
      >
        {files.map((file, index) => (
          <li key={index} className='relative'>
            <div className='animate-pulse group aspect-h-2 aspect-w-14 block w-full overflow-hidden rounded-lg bg-gray-400 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100'>
            </div>
          </li>
        ))}
      </ul>
    );
  }
  