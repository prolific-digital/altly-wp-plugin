export default function Example({ text }) {
  return (
    <header>
      <div className='md:flex md:items-center md:justify-between mx-auto max-w-7xl mb-12'>
        <div className='min-w-0 flex-1'>
          <h1 className='text-3xl font-bold leading-tight tracking-tight text-gray-900'>
            {text}
          </h1>
        </div>
        {/* <div className='mt-4 flex md:ml-4 md:mt-0'>
          <button
            type='button'
            className='inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
          >
            Edit
          </button>
          <button
            type='button'
            className='ml-3 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
          >
            Publish
          </button>
        </div> */}
      </div>
    </header>
  );
}
