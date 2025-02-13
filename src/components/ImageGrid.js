// src/components/ImageGrid.js
import React from "react";

export default function ImageGrid({ files }) {
  return (
    <ul
      role="list"
      className="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8"
    >
      {files.map((file) => {
        const filename = file.filePath.split("/").pop();
        return (
          <li key={file.id} className="relative">
            <div className="group overflow-hidden rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100">
              <a href={file.link} target="_blank" rel="noopener noreferrer">
                <img
                  alt=""
                  src={file.src}
                  className="pointer-events-none aspect-[10/7] object-cover group-hover:opacity-75"
                />
              </a>
              {file.queued && (
                <span className="absolute top-2 right-2 rounded bg-yellow-500 px-2 py-1 text-xs font-semibold text-white">
                  Queued
                </span>
              )}
            </div>
            <p className="mt-2 truncate text-sm font-medium text-gray-900">
              {filename}
            </p>
            <p className="mt-1 text-xs text-gray-500">{file.size}</p>
          </li>
        );
      })}
    </ul>
  );
}
