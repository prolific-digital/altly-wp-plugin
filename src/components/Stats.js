import React from "react";

export default function Stats({
  totalImages,
  missingAltCount,
  queuedCount,
  creditsLeft,
}) {
  const stats = [
    { name: "Total Images", stat: totalImages },
    { name: "Missing Alt Text", stat: missingAltCount },
    { name: "Queued Images", stat: queuedCount },
    { name: "Credits", stat: creditsLeft },
  ];

  return (
    <div className="mb-8">
      <h3 className="text-base font-semibold text-gray-900">
        Media Library Stats
      </h3>
      <dl className="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-4">
        {stats.map((item) => (
          <div
            key={item.name}
            className="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6"
          >
            <dt className="truncate text-sm font-medium text-gray-500">
              {item.name}
            </dt>
            <dd className="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
              {item.stat}
            </dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
