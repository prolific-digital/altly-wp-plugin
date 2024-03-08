function classNames(...classes) {
  return classes.filter(Boolean).join(' ');
}

export default function Example({
  score,
  totalImages,
  missingAltText,
  credits,
}) {
  return (
    <dl className='mx-auto mb-10 grid grid-cols-1 gap-px bg-gray-900/5 sm:grid-cols-2 lg:grid-cols-3'>
      <div className='flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 bg-white px-4 py-10 sm:px-6 xl:px-8'>
        <dt className='text-sm font-medium leading-6 text-gray-500'>
          Total Images
        </dt>
        <dd className='w-full flex-none text-3xl font-medium leading-10 tracking-tight text-gray-900'>
          {totalImages}
        </dd>
      </div>
      <div className='flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 bg-white px-4 py-10 sm:px-6 xl:px-8'>
        <dt className='text-sm font-medium leading-6 text-gray-500'>
          Missing Alt Text
        </dt>
        <dd className='w-full flex-none text-3xl font-medium leading-10 tracking-tight text-gray-900'>
          {missingAltText}
        </dd>
      </div>
      {/* <div className='flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 bg-white px-4 py-10 sm:px-6 xl:px-8'>
        <dt className='text-sm font-medium leading-6 text-gray-500'>
          Confidence Score
        </dt>
        <dd className='w-full flex-none text-3xl font-medium leading-10 tracking-tight text-gray-900'>
          {score}
        </dd>
      </div> */}
      <div className='flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 bg-white px-4 py-10 sm:px-6 xl:px-8'>
        <dt className='text-sm font-medium leading-6 text-gray-500'>
          Credits Remaining
        </dt>
        <dd className='w-full flex-none text-3xl font-medium leading-10 tracking-tight text-gray-900'>
          {credits}
        </dd>
      </div>
    </dl>
  );
}
