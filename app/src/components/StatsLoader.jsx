const files = [
    { title: 'Placeholder' },
  ];
  
  export default function StatsLoader() {
    return (
      <ul
        role='list'
        className=''
      >
        {files.map((file, index) => (
          <li key={index} className='relative'>
            <div className='animate-pulse group aspect-h-2 mb-10 aspect-w-16 block w-full overflow-hidden rounded-lg bg-gray-400 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100'>
            </div>
          </li>
        ))}
      </ul>
    );
  }
  