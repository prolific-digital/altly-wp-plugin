import React, { useState, useEffect } from "react";
import {
  HomeIcon,
  Cog8ToothIcon,
  ArrowTopRightOnSquareIcon,
} from "@heroicons/react/24/outline";
import { UserCircleIcon } from "@heroicons/react/24/solid";
import ImageGrid from "./ImageGrid";
import Settings from "./Settings";
import Stats from "./Stats";
import Pagination from "./Pagination";
import EmptyState from "./EmptyState";

// Import our notice components
import CreditExhaustedNotice from "./CreditExhaustedNotice";
import CreditLowNotice from "./CreditLowNotice";

const API_VALIDATE_URL = process.env.REACT_APP_API_VALIDATE_URL;
const API_QUEUE_URL = process.env.REACT_APP_API_QUEUE_URL;

export default function Shell() {
  const [view, setView] = useState("dashboard");
  const [images, setImages] = useState([]);
  const [loadingImages, setLoadingImages] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalImages, setTotalImages] = useState(0);
  const [missingAltCount, setMissingAltCount] = useState(0);
  const [bulkGenerating, setBulkGenerating] = useState(false);
  const [bulkMessage, setBulkMessage] = useState("");
  const [userCredits, setUserCredits] = useState("N/A");
  const [bulkProgress, setBulkProgress] = useState(0);
  const [queuedCount, setQueuedCount] = useState(0);

  const itemsPerPage = 12;

  // Fetch images and stats.
  const fetchImages = async () => {
    setLoadingImages(true);
    try {
      const res = await fetch(
        AltlySettings.restUrl +
          "images?page=" +
          currentPage +
          "&per_page=" +
          itemsPerPage,
        { headers: { "X-WP-Nonce": AltlySettings.nonce } }
      );
      const data = await res.json();
      setImages(data.images);
      setTotalImages(data.stats.total_images);
      setMissingAltCount(data.stats.missing_alt_count);
      setQueuedCount(data.stats.queued_count);
      const totalPagesHeader = res.headers.get("X-WP-TotalPages");
      setTotalPages(totalPagesHeader ? parseInt(totalPagesHeader, 10) : 1);
    } catch (error) {
      console.error("Error fetching images:", error);
    }
    setTimeout(() => setLoadingImages(false), 300);
  };

  // Fetch user credits from the validation endpoint.
  const fetchCredits = async () => {
    if (AltlySettings.apiKey) {
      try {
        const res = await fetch(API_VALIDATE_URL, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + AltlySettings.apiKey,
          },
        });
        if (res.ok) {
          const data = await res.json();
          if (data.data && data.data.credits != null) {
            setUserCredits(Number(data.data.credits));
          }
        }
      } catch (error) {
        console.error("Error fetching credits:", error);
      }
    }
  };

  useEffect(() => {
    if (view === "dashboard") {
      fetchImages();
      fetchCredits();
    }
  }, [view, currentPage]);

  // Determine if all images on the current page are queued.
  const allQueued =
    images.length > 0 && images.every((img) => img.queued === true);

  // Handler for Bulk Generate button.
  const handleBulkGenerate = async () => {
    // Check if credits are exhausted.
    if (typeof userCredits === "number" && userCredits <= 0) {
      setBulkMessage(
        "Your credits are exhausted. Please visit your account to purchase additional credits."
      );
      return;
    }

    setBulkGenerating(true);
    setBulkMessage("");
    setBulkProgress(0);

    try {
      const resAll = await fetch(`${AltlySettings.restUrl}images?per_page=-1`, {
        headers: { "X-WP-Nonce": AltlySettings.nonce },
      });
      const dataAll = await resAll.json();
      const allImages = dataAll.images;
      let imagesToQueue = allImages.filter((img) => !img.queued);

      // If credits are less than available images, only process as many as credits allow.
      if (
        typeof userCredits === "number" &&
        imagesToQueue.length > userCredits
      ) {
        imagesToQueue = imagesToQueue.slice(0, userCredits);
      }

      if (imagesToQueue.length === 0) {
        setBulkMessage("No images available for bulk generation.");
        setBulkGenerating(false);
        return;
      }

      let completed = 0;
      const queuePromises = imagesToQueue.map(async (image) => {
        try {
          const resBlob = await fetch(image.src);
          const blob = await resBlob.blob();
          const formData = new FormData();
          const filename = image.filePath.split("/").pop();
          formData.append("file", blob, filename);
          formData.append("platform_id", "sandbox");
          formData.append("platform_url", window.location.origin);
          formData.append("api_key", AltlySettings.apiKey);
          formData.append("image_id", image.id);

          const resQueue = await fetch(API_QUEUE_URL, {
            method: "POST",
            headers: { Authorization: "Bearer " + AltlySettings.apiKey },
            body: formData,
          });
          const dataQueue = await resQueue.json();
          if (dataQueue.success) {
            await fetch(`${AltlySettings.restUrl}mark-queued`, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": AltlySettings.nonce,
              },
              body: JSON.stringify({ image_id: image.id }),
            });
            return { id: image.id, status: "queued" };
          } else {
            return { id: image.id, status: "failed" };
          }
        } catch (error) {
          console.error("Error enqueuing image:", error);
          return { id: image.id, status: "failed" };
        } finally {
          completed++;
          setBulkProgress((completed / imagesToQueue.length) * 100);
        }
      });

      const results = await Promise.all(queuePromises);
      const queuedCount = results.filter((r) => r.status === "queued").length;
      const failedCount = results.filter((r) => r.status !== "queued").length;
      setBulkMessage(
        `Bulk generation completed: ${queuedCount} images queued, ${failedCount} failed.`
      );
    } catch (error) {
      console.error("Error fetching all missing images:", error);
      setBulkMessage("Error fetching images for bulk generation.");
    }

    setBulkGenerating(false);
    fetchImages(); // Refresh the dashboard.
  };

  // Handler for Clear Queue button.
  const handleClearQueue = async () => {
    try {
      const resAll = await fetch(`${AltlySettings.restUrl}images?per_page=-1`, {
        headers: { "X-WP-Nonce": AltlySettings.nonce },
      });
      const dataAll = await resAll.json();
      const allImages = dataAll.images;

      if (allImages.length === 0) {
        setBulkMessage("No images to clear.");
        return;
      }

      const image_ids = allImages.map((img) => img.id);
      const res = await fetch(AltlySettings.restUrl + "clear-queue", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": AltlySettings.nonce,
        },
        body: JSON.stringify({ image_ids }),
      });
      const data = await res.json();
      if (data.success) {
        setBulkMessage("Queue cleared for all images.");
        fetchImages();
      } else {
        setBulkMessage("Failed to clear queue.");
      }
    } catch (error) {
      console.error("Error clearing queue:", error);
      setBulkMessage("Error clearing queue.");
    }
  };

  return (
    <div className="min-h-full">
      {/* Top Header with Navigation and "View Account" Link */}
      <header className="bg-white shadow">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
          <div className="flex items-center space-x-4">
            <button
              onClick={() => {
                setView("dashboard");
                setCurrentPage(1);
              }}
              className={`flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                view === "dashboard"
                  ? "bg-indigo-500 text-white"
                  : "text-gray-700 hover:bg-gray-200"
              }`}
            >
              <HomeIcon className="h-5 w-5 mr-1" />
              Dashboard
            </button>
            <button
              onClick={() => setView("settings")}
              className={`flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                view === "settings"
                  ? "bg-indigo-500 text-white"
                  : "text-gray-700 hover:bg-gray-200"
              }`}
            >
              <Cog8ToothIcon className="h-5 w-5 mr-1" />
              Settings
            </button>
            <a
              href="https://prolificdigital.notion.site/Altly-User-Documention-19b5efcd8c5f807cbd9bdfd14bfe2c52?pvs=4"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-200"
            >
              <ArrowTopRightOnSquareIcon className="h-4 w-4 mr-1" />
              Documentation
            </a>
          </div>
          <div className="flex items-center space-x-4">
            <a
              href="https://app.altly.io/dashboard"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center text-gray-700 hover:text-indigo-500"
            >
              <UserCircleIcon className="h-6 w-6 mr-1" />
              <span className="text-sm font-medium">View Account</span>
            </a>
            <a
              href="https://altly.ai"
              target="_blank"
              className="flex-shrink-0 w-20"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="100%"
                height="64"
                viewBox="0 0 124 64"
                fill="none"
              >
                {/* Your SVG paths here */}
              </svg>
            </a>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="py-10">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {view === "dashboard" ? (
            <>
              <h1 className="text-3xl font-bold mb-6">Dashboard</h1>

              {/* Display Credit Notices */}
              {typeof userCredits === "number" &&
                (userCredits <= 0 ? (
                  <CreditExhaustedNotice />
                ) : userCredits < 20 ? (
                  <CreditLowNotice credits={userCredits} />
                ) : null)}

              {bulkMessage && (
                <p className="mb-2 text-sm text-gray-700 text-right">
                  {bulkMessage}
                </p>
              )}
              <div className="flex justify-end mb-4 space-x-2">
                <button
                  onClick={handleBulkGenerate}
                  disabled={bulkGenerating || allQueued}
                  className={`rounded-md px-4 py-2 text-sm font-semibold focus:outline-none ${
                    bulkGenerating || allQueued
                      ? "bg-gray-400 cursor-not-allowed"
                      : "bg-green-600 hover:bg-green-500 text-white"
                  }`}
                >
                  {bulkGenerating ? "Bulk generating..." : "Bulk Generate"}
                </button>
                <button
                  onClick={handleClearQueue}
                  disabled={bulkGenerating}
                  className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 focus:outline-none"
                >
                  Clear Queue
                </button>
              </div>
              {bulkGenerating && (
                <>
                  <div className="w-full bg-gray-200 h-2 rounded mb-1">
                    <div
                      className="bg-blue-500 h-2 rounded transition-all duration-300"
                      style={{ width: `${bulkProgress}%` }}
                    ></div>
                  </div>
                  <div className="text-center text-xs text-gray-700 mb-4">
                    {Math.round(bulkProgress)}%
                  </div>
                </>
              )}

              <Stats
                totalImages={totalImages}
                missingAltCount={missingAltCount}
                queuedCount={queuedCount}
                creditsLeft={userCredits}
              />
              {loadingImages ? (
                <div className="flex justify-center items-center h-64 transition-opacity duration-300">
                  <p className="text-lg text-gray-700">Loading images…</p>
                </div>
              ) : (
                <>
                  <div className="transition-opacity duration-300 opacity-100">
                    {missingAltCount === 0 ? (
                      <EmptyState />
                    ) : (
                      <ImageGrid files={images} />
                    )}
                  </div>

                  {missingAltCount > 0 && (
                    <Pagination
                      currentPage={currentPage}
                      itemsPerPage={itemsPerPage}
                      totalResults={missingAltCount}
                      totalPages={totalPages}
                      onPageChange={(newPage) => setCurrentPage(newPage)}
                    />
                  )}
                </>
              )}
            </>
          ) : (
            <>
              <h1 className="text-3xl font-bold mb-6">Settings</h1>
              <Settings
                onUpdateCredits={(credits) => setUserCredits(Number(credits))}
              />
            </>
          )}
        </div>
      </main>
    </div>
  );
}
