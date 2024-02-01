import React from 'react';
import {
  ExclamationCircleIcon,
  CheckCircleIcon,
} from '@heroicons/react/20/solid';

export default function Input({
  label,
  type,
  placeholder,
  name,
  value,
  onChange,
  isError,
  isValueCorrect,
  successMessage,
  disabled,
}) {
  const isEmpty = value.trim() === '';

  return (
    <div className='mb-10'>
      <label
        htmlFor={name}
        className='block text-sm font-medium leading-6 text-gray-900'
      >
        {label}
      </label>
      <div className='relative mt-2 rounded-md shadow-sm'>
        <input
          type={type}
          name={name}
          disabled={disabled}
          id={name}
          className={`block w-full rounded-md border-0 py-1.5 pr-10 ${
            isEmpty
              ? 'text-red-900 ring-1 ring-inset ring-red-300 placeholder:text-red-300 focus:ring-2 focus:ring-inset focus:ring-red-500'
              : isValueCorrect
              ? 'text-green-900 ring-1 ring-inset ring-green-300 placeholder:text-green-300 focus:ring-2 focus:ring-inset focus:ring-green-500'
              : 'text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-500'
          } sm:text-sm sm:leading-6`}
          placeholder={placeholder}
          aria-invalid={isEmpty || isError}
          aria-describedby={`${name}-error`}
          value={value}
          onChange={onChange}
        />
        <div className='pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3'>
          {isEmpty ? (
            <ExclamationCircleIcon
              className='h-5 w-5 text-red-500'
              aria-hidden='true'
            />
          ) : isValueCorrect ? (
            <CheckCircleIcon
              className='h-5 w-5 text-green-500'
              aria-hidden='true'
            />
          ) : (
            <ExclamationCircleIcon
              className='h-5 w-5 text-gray-500'
              aria-hidden='true'
            />
          )}
        </div>
      </div>
      {isEmpty ? (
        <p className='mt-2 text-sm text-red-600' id={`${name}-error`}>
          Field cannot be empty.
        </p>
      ) : isError ? (
        <p className='mt-2 text-sm text-red-600' id={`${name}-error`}>
          Value is not correct.
        </p>
      ) : isValueCorrect ? (
        <p className='mt-2 text-sm text-green-600'>{successMessage}</p>
      ) : null}
    </div>
  );
}
