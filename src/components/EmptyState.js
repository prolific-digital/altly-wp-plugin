import { CheckCircleIcon } from "@heroicons/react/24/outline";

export default function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center w-full p-12 mb-10 border-2 border-dashed border-gray-300 rounded-lg text-center">
      <CheckCircleIcon className="size-12 text-green-500" />
      <p className="mt-4 text-sm font-semibold text-gray-900">
        All images have alt text.
      </p>
    </div>
  );
}
