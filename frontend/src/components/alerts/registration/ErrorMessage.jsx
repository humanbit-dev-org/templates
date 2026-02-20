// Reusable error message template component
export default function ErrorMessage({ type }) {
	const messages = {
		required: "This field is required.",
		min: "Value is too low.",
		max: "Value is too high.",
		invalid: "Invalid value.",
	};

	if (!type) return null;
	return <div className="error-message">{messages[type] || "Error"}</div>;
}
