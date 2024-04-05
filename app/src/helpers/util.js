import getBaseUrl from '../helpers/baseUrlHelper';

export const getLicenseKey = async () => {
    try {
        const response = await fetch(
            getBaseUrl() + '/wp-json/altly/v1/license-key'
        );
        const data = await response.json();

        if (!data.license_key) {
            throw new Error('License key not found');
        }

        return data.license_key;
    } catch (error) {
        console.error('Error while loading the license key:', error);
    }
};