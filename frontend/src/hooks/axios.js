import Axios from "axios";

const axios = Axios.create({
	baseURL: process.env.NEXT_PUBLIC_ADMIN_URL,
	headers: {
		"X-Requested-With": "XMLHttpRequest",
	},
	withCredentials: true,
	withXSRFToken: true,
});

export default axios;
