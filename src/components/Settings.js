import React, { useState } from "react";

const API_VALIDATE_URL = process.env.REACT_APP_API_VALIDATE_URL;
const API_QUEUE_URL = process.env.REACT_APP_API_QUEUE_URL;

export default function Settings({ onUpdateCredits }) {
  const [apiKey, setApiKey] = useState(AltlySettings.apiKey || "");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);
  const [clearingAlt, setClearingAlt] = useState(false);
  const [clearMessage, setClearMessage] = useState("");

  const validateApiKey = (key) => key && key.trim().length > 0;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSuccess("");
    if (!validateApiKey(apiKey)) {
      setError("Please enter a valid API key.");
      return;
    }
    setLoading(true);
    try {
      const resValidation = await fetch(API_VALIDATE_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: "Bearer " + apiKey,
        },
      });

      if (!resValidation.ok) {
        setError("Invalid API key.");
        setLoading(false);
        return;
      }

      const validationData = await resValidation.json();
      if (!validationData.data) {
        setError("Invalid API key.");
        setLoading(false);
        return;
      }

      if (onUpdateCredits) {
        onUpdateCredits(validationData.data.credits);
      }

      const resSave = await fetch(AltlySettings.restUrl + "save-key", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": AltlySettings.nonce,
        },
        body: JSON.stringify({ licenseKey: apiKey }),
      });

      const saveData = await resSave.json();
      if (saveData.success) {
        setSuccess("API key saved successfully.");
      } else {
        setError(
          saveData.message || "An error occurred while saving the API key."
        );
      }
    } catch (err) {
      console.error("Error saving API key:", err);
      setError("An error occurred while saving the API key.");
    }
    setLoading(false);
  };

  const handleClearAltText = async () => {
    setClearingAlt(true);
    setClearMessage("");

    try {
      const res = await fetch(AltlySettings.restUrl + "clear-alt-text", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": AltlySettings.nonce,
        },
      });

      const data = await res.json();
      if (data.success) {
        setClearMessage("Alt text has been cleared for all images.");
      } else {
        setClearMessage("Failed to clear alt text.");
      }
    } catch (error) {
      console.error("Error clearing alt text:", error);
      setClearMessage("An error occurred while clearing alt text.");
    }

    setClearingAlt(false);
  };

  return (
    <form onSubmit={handleSubmit}>
      <div className="space-y-12">
        <div className="border-b border-gray-900/10 pb-12">
          <h2 className="text-base font-semibold text-gray-900">
            API Settings
          </h2>
          <p className="mt-1 text-sm text-gray-600">
            Enter your API key below. This will be securely stored in your
            WordPress options.
          </p>

          <div className="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div className="sm:col-span-4">
              <label
                htmlFor="api-key"
                className="block text-sm font-medium text-gray-900"
              >
                API License Key
              </label>
              <div className="mt-2">
                <input
                  id="api-key"
                  name="api-key"
                  type="password"
                  value={apiKey}
                  onChange={(e) => setApiKey(e.target.value)}
                  placeholder="your-api-key"
                  className="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 placeholder:text-gray-400 outline-1 outline-gray-300 focus:outline-2 focus:outline-indigo-600"
                />
              </div>
              {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
              {success && (
                <p className="mt-2 text-sm text-green-600">{success}</p>
              )}
            </div>
          </div>
        </div>

        <div className="flex items-center justify-end gap-x-6">
          <button
            type="submit"
            disabled={loading}
            className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
          >
            {loading ? "Saving..." : "Save"}
          </button>
        </div>

        {/* Clear Alt Text Button */}
        <div className="mt-8 border-t border-gray-200 pt-6">
          <h2 className="text-base font-semibold text-gray-900">
            Clear Alt Text
          </h2>
          <p className="mt-1 text-sm text-gray-600">
            Remove alt text from all images. This action cannot be undone.
          </p>

          <button
            type="button"
            onClick={handleClearAltText}
            disabled={clearingAlt}
            className="mt-4 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
          >
            {clearingAlt ? "Clearing..." : "Clear Alt Text"}
          </button>

          {clearMessage && (
            <p className="mt-2 text-sm text-gray-700">{clearMessage}</p>
          )}
        </div>
      </div>
    </form>
  );
}
