export default function Pagination({
  currentPage,
  itemsPerPage,
  totalItems,
  handlePreviousPage,
  handleNextPage,
}) {
  const startIndex = (currentPage - 1) * itemsPerPage + 1;
  const endIndex = Math.min(startIndex + itemsPerPage - 1, totalItems);

  return (
    <nav
      className='mt-10 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6'
      aria-label='Pagination'
    >
      <div className='hidden sm:block'>
        <p className='text-sm text-gray-700'>
          Showing <span className='font-medium'>{startIndex}</span> to{' '}
          <span className='font-medium'>{endIndex}</span> of{' '}
          <span className='font-medium'>{totalItems}</span> results
        </p>
      </div>
      <div className='flex flex-1 justify-between sm:justify-end'>
        <button
          onClick={handlePreviousPage}
          className='relative inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline-offset-0'
        >
          Previous
        </button>
        <button
          onClick={handleNextPage}
          className='relative ml-3 inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline-offset-0'
        >
          Next
        </button>
      </div>
    </nav>
  );
}
