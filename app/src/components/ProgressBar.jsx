export default function Example({ text, percentage }) {
  return (
    <div className='mb-10'>
      <h4 className='sr-only'>Status</h4>
      <p className='text-sm font-medium text-gray-900'>{text}</p>
      <div className='mt-6' aria-hidden='true'>
        <div className='overflow-hidden rounded-full bg-gray-200'>
          <div
            className='h-2 rounded-full bg-indigo-600'
            style={{ width: percentage }}
          />
        </div>
      </div>
    </div>
  );
}
