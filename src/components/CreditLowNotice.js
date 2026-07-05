import { ExclamationTriangleIcon } from "@heroicons/react/20/solid";

export default function CreditLowNotice({ credits }) {
  return (
    <div className="rounded-md bg-yellow-50 p-4 mb-4">
      <div className="flex">
        <div className="shrink-0">
          <ExclamationTriangleIcon
            aria-hidden="true"
            className="h-5 w-5 text-yellow-400"
          />
        </div>
        <div className="ml-3">
          <h3 className="text-sm font-medium text-yellow-800">
            You are running low on credits.
          </h3>
          <div className="mt-2 text-sm text-yellow-700">
            <p>
              You have {credits} credit{credits === 1 ? "" : "s"} remaining.
              Please{" "}
              <a
                href="https://app.altly.io/login"
                className="underline font-bold text-yellow-800"
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
