import { XCircleIcon } from "@heroicons/react/20/solid";

export default function CreditExhaustedNotice() {
  return (
    <div className="rounded-md bg-red-50 p-4 mb-4">
      <div className="flex">
        <div className="shrink-0">
          <XCircleIcon aria-hidden="true" className="h-5 w-5 text-red-400" />
        </div>
        <div className="ml-3">
          <h3 className="text-sm font-medium text-red-800">
            Your credits are exhausted.
          </h3>
          <div className="mt-2 text-sm text-red-700">
            <p>
              Your credits are exhausted. Please{" "}
              <a
                href="https://app.altly.io/login"
                className="underline font-bold text-red-800"
                target="_blank"
              >
                log in to your account
              </a>{" "}
              to purchase additional credits.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
