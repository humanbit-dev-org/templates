export default function Loading() {
	return (
		<div className="tw:fixed tw:inset-0 tw:flex tw:items-center tw:justify-center tw:bg-white tw:z-[9999] tw:h-screen tw:w-full">
			<div className="tw:flex tw:flex-col tw:items-center tw:justify-center tw:gap-6">
				<div className="tw:relative tw:w-20 tw:h-20">
					<div className="tw:absolute tw:inset-0 tw:border-4 tw:border-gray-200 tw:border-t-[#003a53] tw:border-r-[#003a53] tw:rounded-full tw:animate-spin"></div>
					<div className="tw:absolute tw:inset-0 tw:m-auto tw:w-12 tw:h-12 tw:border-4 tw:border-gray-200 tw:border-t-[#003a53] tw:border-l-[#003a53] tw:rounded-full tw:animate-spin tw:animate-reverse"></div>
				</div>

				<div className="tw:flex tw:flex-col tw:items-center">
					<p className="small tw:text-[#003a53] tw:font-semibold tw:mb-1">Loading...</p>
				</div>

				<div className="tw:flex tw:space-x-1">
					<span
						className="tw:w-2 tw:h-2 tw:bg-[#003a53] tw:rounded-full tw:animate-bounce"
						style={{ animationDelay: "0ms" }}></span>
					<span
						className="tw:w-2 tw:h-2 tw:bg-[#003a53] tw:rounded-full tw:animate-bounce"
						style={{ animationDelay: "150ms" }}></span>
					<span
						className="tw:w-2 tw:h-2 tw:bg-[#003a53] tw:rounded-full tw:animate-bounce"
						style={{ animationDelay: "300ms" }}></span>
				</div>
			</div>
		</div>
	);
}
