let isDraggingEnabled = false;
let draggedRow = null;
let originalOrder;
let hasChanges = false;

let enableButton = document.getElementById("enable-draggable");
let saveButton = document.getElementById("save-draggable");
let cancelButton = document.getElementById("cancel-draggable");

let first_color = "#00627C";
let second_color = "rgb(101, 149, 197)";

enableButton.addEventListener("click", function () {
	const rows = document.querySelectorAll("#crudTable tbody tr");

	if (isDraggingEnabled) {
		enableButton.style.display = "";
		saveButton.style.display = "none";
		cancelButton.style.display = "none";
		isDraggingEnabled = false;

		rows.forEach((row) => {
			row.draggable = false;
			row.style.cursor = "";
			row.style.border = "";
			row.style.backgroundColor = "";
			row.removeEventListener("dragstart", handleDragStart);
			row.removeEventListener("dragover", handleDragOver);
			row.removeEventListener("drop", handleDrop);
			row.removeEventListener("dragend", handleDragEnd);
		});
	} else {
		enableButton.style.display = "none";
		saveButton.style.display = "";
		cancelButton.style.display = "";
		isDraggingEnabled = true;
		originalOrder = Array.from(document.querySelectorAll("#crudTable tbody tr")); // Store original row order

		rows.forEach((row) => {
			row.draggable = true;
			row.style.cursor = "grab"; // Cursor remains as "grab" during drag

			// Apply all borders and set a transparent background to allow visibility during dragging
			row.style.border = "1px dashed " + first_color; // Set all borders clearly to visible
			row.style.backgroundColor = ""; // Default background
			row.style.transition = "border 0.5s ease";
			row.style.padding = "10px"; // To ensure visible content during drag

			row.addEventListener("dragstart", handleDragStart);
			row.addEventListener("dragover", handleDragOver);
			row.addEventListener("drop", handleDrop);
			row.addEventListener("dragend", handleDragEnd);
		});
	}
});

saveButton.addEventListener("click", function () {
	let form = document.getElementById("sortForm");
	let sortInput = document.getElementById("newSortOrder");
	enableButton.style.display = "";
	enableButton.setAttribute("disabled", true);
	saveButton.style.display = "none";
	cancelButton.style.display = "none";
	isDraggingEnabled = false;
	hasChanges = false; // Reset change tracking

	saveButton.style.pointerEvents = "none"; // Disable pointer events
	saveButton.style.opacity = "0.5";

	const rows = document.querySelectorAll("#crudTable tbody tr");
	let idVal = [];
	Array.from(rows).map((row) => {
		const cells = row.querySelectorAll("td");
		idVal.push(cells[0].textContent.replace(/\s+/g, ""));
	});

	sortInput.value = idVal;
	console.log(sortInput.value);
	rows.forEach((row) => {
		row.draggable = false;
		row.style.cursor = "";
		row.style.border = "";
		row.style.backgroundColor = "";
		row.removeEventListener("dragstart", handleDragStart);
		row.removeEventListener("dragover", handleDragOver);
		row.removeEventListener("drop", handleDrop);
		row.removeEventListener("dragend", handleDragEnd);
	});

	const currentPath = window.location.pathname;
	const lastPart = currentPath.substring(currentPath.lastIndexOf("/") + 1);
	const formAction = `${lastPart}/sort`;
	form.action = formAction;
	form.submit();
});

cancelButton.addEventListener("click", function () {
	enableButton.style.display = "";
	saveButton.style.display = "none";
	cancelButton.style.display = "none";
	isDraggingEnabled = false;
	hasChanges = false; // Reset change tracking

	saveButton.style.pointerEvents = "none"; // Disable pointer events
	saveButton.style.opacity = "0.5";

	const rows = document.querySelectorAll("#crudTable tbody tr");
	rows.forEach((row, index) => {
		row.draggable = false;
		row.style.cursor = "";
		row.style.border = "";
		row.style.backgroundColor = "";
		row.parentNode.appendChild(originalOrder[index]); // Restore rows to original order
		row.removeEventListener("dragstart", handleDragStart);
		row.removeEventListener("dragover", handleDragOver);
		row.removeEventListener("drop", handleDrop);
		row.removeEventListener("dragend", handleDragEnd);
	});

	console.log("Rows restored to original order.");
});

// Tracking changes during drag and drop
function handleDragStart(e) {
	draggedRow = this;

	// Apply visual dragging effect: background and all borders persist
	draggedRow.style.backgroundColor = second_color; // Highlight background during drag
	draggedRow.style.transition = "background 0.5s ease";
}

function handleDragOver(e) {
	e.preventDefault();

	const targetRow = e.target.closest("tr");

	if (targetRow && targetRow !== draggedRow) {
		const rect = targetRow.getBoundingClientRect();
		const isAfter = e.clientY > rect.top + rect.height / 2;

		const tbody = targetRow.parentNode;
		if (isAfter) {
			tbody.insertBefore(draggedRow, targetRow.nextSibling);
		} else {
			tbody.insertBefore(draggedRow, targetRow);
		}

		hasChanges = true; // Mark changes when dragging occurs
	}
}

function handleDrop(e) {
	e.preventDefault(); // Necessary to allow row reordering
}

function handleDragEnd() {
	if (draggedRow) {
		draggedRow.style.cursor = "grab"; // Reset cursor to grab after dragging
		draggedRow.style.backgroundColor = ""; // Clear background after dragging
		draggedRow.style.border = "1px dashed " + first_color;
		draggedRow = null;
	}

	// Update save button state based on hasChanges flag
	if (hasChanges) {
		saveButton.style.pointerEvents = "auto"; // Enable pointer events
		saveButton.style.opacity = "1"; // Set opacity to 1
	} else {
		saveButton.style.pointerEvents = "none"; // Disable pointer events
		saveButton.style.opacity = "0.5"; // Set opacity to 0.5
	}
}
