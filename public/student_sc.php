<script>
        // Initialize table with all entries visible by default
        function initializeTable() {
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            rows.forEach(row => {
                row.style.display = ''; // Ensure all rows are visible
            });
        }

        // Call the initialize function on page load
        initializeTable();

        // Entries per page functionality
        document.getElementById('entries').addEventListener('change', function() {
            const selectedValue = this.value;
            const rows = document.querySelectorAll('#sitinTable tbody tr');

            if (selectedValue === "all") {
                rows.forEach(row => {
                    row.style.display = ''; // Show all rows
                });
            } else {
                const numEntries = parseInt(selectedValue);
                rows.forEach((row, index) => {
                    if (index < numEntries) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#sitinTable tbody tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let match = false;

                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(searchValue)) {
                        match = true;
                    }
                });

                row.style.display = match ? '' : 'none';
            });
        });

        // Filter functionality
        const courseFilter = document.getElementById('courseFilter');
        const levelFilter = document.getElementById('levelFilter');

        function filterTable() {
            const courseValue = courseFilter.value.toLowerCase();
            const levelValue = levelFilter.value.toLowerCase();
            const rows = document.querySelectorAll('#sitinTable tbody tr');

            rows.forEach(row => {
                const courseCell = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const levelCell = row.querySelector('td:nth-child(4)').textContent.toLowerCase();

                const matchesCourse = courseValue ? courseCell.includes(courseValue) : true;
                const matchesLevel = levelValue ? levelCell.includes(levelValue) : true;

                row.style.display = matchesCourse && matchesLevel ? '' : 'none';
            });
        }

        courseFilter.addEventListener('change', filterTable);
        levelFilter.addEventListener('change', filterTable);

        // Toggle Filter Dropdown
        document.getElementById('filterButton').addEventListener('click', function() {
            const dropdown = document.getElementById('filterDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Close Filter Dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const filterButton = document.getElementById('filterButton');
            const filterDropdown = document.getElementById('filterDropdown');
            if (!filterButton.contains(event.target) && !filterDropdown.contains(event.target)) {
                filterDropdown.classList.add('hidden');
            }
        });

        // Sort functionality
        document.getElementById('sortButton').addEventListener('click', function() {
            const dropdown = document.getElementById('sortDropdown');
            dropdown.classList.toggle('hidden');
        });

        document.querySelectorAll('#sortDropdown a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sortType = this.getAttribute('data-sort');
                const rows = Array.from(document.querySelectorAll('#sitinTable tbody tr'));

                rows.sort((a, b) => {
                    if (sortType === 'asc') {
                        const aText = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        const bText = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        return aText.localeCompare(bText);
                    } else if (sortType === 'desc') {
                        const aText = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        const bText = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        return bText.localeCompare(aText);
                    } else if (sortType === 'newest') {
                        const aDate = new Date(a.querySelector('td:nth-child(7)').textContent);
                        const bDate = new Date(b.querySelector('td:nth-child(7)').textContent);
                        return bDate - aDate;
                    } else if (sortType === 'oldest') {
                        const aDate = new Date(a.querySelector('td:nth-child(7)').textContent);
                        const bDate = new Date(b.querySelector('td:nth-child(7)').textContent);
                        return aDate - bDate;
                    }
                });

                const tbody = document.querySelector('#sitinTable tbody');
                tbody.innerHTML = '';
                rows.forEach(row => tbody.appendChild(row));
            });
        });

        // Close Sort Dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const sortButton = document.getElementById('sortButton');
            const sortDropdown = document.getElementById('sortDropdown');
            if (!sortButton.contains(event.target) && !sortDropdown.contains(event.target)) {
                sortDropdown.classList.add('hidden');
            }
        });

        // Export to CSV
        // Export to CSV
        document.getElementById('exportCSV').addEventListener('click', function() {
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            let csvContent = "data:text/csv;charset=utf-8,";
            const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(th => th.textContent)
                .join(',');
            csvContent += headers + "\n";

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const rowData = Array.from(row.querySelectorAll('td'))
                        .slice(0, -1) // Exclude the last column (Action)
                        .map(td => td.textContent)
                        .join(',');
                    csvContent += rowData + "\n";
                }
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "student_records.csv");
            document.body.appendChild(link);
            link.click();
        });

        // Export to Excel
        // Export to Excel
        document.getElementById('exportExcel').addEventListener('click', function() {
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            const data = [];
            const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(th => th.textContent);
            data.push(headers);

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const rowData = Array.from(row.querySelectorAll('td'))
                        .slice(0, -1) // Exclude the last column (Action)
                        .map(td => td.textContent);
                    data.push(rowData);
                }
            });

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
            XLSX.writeFile(wb, "student_records.xlsx");
        });

        // Export to PDF
        // Export to PDF
        document.getElementById('exportPDF').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'pt', 'a4');

            const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(th => th.textContent);
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            const data = [];

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const rowData = Array.from(row.querySelectorAll('td'))
                        .slice(0, -1) // Exclude the last column (Action)
                        .map(td => td.textContent);
                    data.push(rowData);
                }
            });

            doc.autoTable({
                head: [headers],
                body: data,
                startY: 20,
                margin: { top: 20 },
                styles: {
                    fontSize: 10,
                    cellPadding: 5,
                    valign: 'middle',
                    halign: 'center',
                    lineColor: [0, 0, 0],
                    lineWidth: 0.1,
                },
                headStyles: {
                    fillColor: false,
                    textColor: [0, 0, 0],
                    fontStyle: 'bold',
                    lineWidth: 0.1,
                },
                bodyStyles: {
                    fillColor: false,
                    textColor: [0, 0, 0],
                    lineWidth: 0.1,
                },
                alternateRowStyles: {
                    fillColor: false,
                },
                columnStyles: {
                    0: { cellWidth: 'auto' },
                    1: { cellWidth: 'auto' },
                    2: { cellWidth: 'auto' },
                    3: { cellWidth: 'auto' },
                    4: { cellWidth: 'auto' },
                    5: { cellWidth: 'auto' },
                },
            });

            doc.save("student_records.pdf");
        });

        // Print Table
        // Print Table
        document.getElementById('printButton').addEventListener('click', function() {
            // Clone the table to avoid modifying the original
            const table = document.getElementById('sitinTable').cloneNode(true);

            // Remove the last column (Action) from the cloned table
            const rows = table.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td, th');
                if (cells.length > 0) {
                    row.removeChild(cells[cells.length - 1]); // Remove the last cell
                }
            });

            // Use printJS to print the modified table
            printJS({
                printable: table,
                type: 'html',
                style: 'table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #000; padding: 8px; text-align: center; }'
            });
        });


        // Reset Session Button
        document.getElementById('resetSession').addEventListener('click', function() {
            if (confirm("Are you sure you want to reset the session for all students?")) {
                fetch('reset_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Session reset successfully!");
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert("Error resetting session: " + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });

        // Edit Student Modal Functions
 // Preview image on file select
document.getElementById("profile-picture-upload").addEventListener("change", function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById("profile-picture-preview").src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Open Edit Modal with current profile picture
function openEditModal(idno) {
    fetch(`get_student.php?idno=${idno}`)
        .then(response => response.json())
        .then(data => {
            // Populate the form fields
            document.getElementById('oldIdNo').value = data.idno; // Old ID (for WHERE clause)
            document.getElementById('editIdNo').value = data.idno; // New ID (for SET clause)
            document.getElementById('editUser').value = data.username;
            document.getElementById('editFirstName').value = data.firstname;
            document.getElementById('editMiddleName').value = data.middlename;
            document.getElementById('editLastName').value = data.lastname;
            document.getElementById('editCourse').value = data.course;
            document.getElementById('editLevel').value = data.level;
            document.getElementById('editEmail').value = data.email;

            // Populate the profile picture preview
            const profilePicturePreview = document.getElementById('profile-picture-preview');
            if (data.profile_picture) {
                profilePicturePreview.src = data.profile_picture;
            } else {
                profilePicturePreview.src = 'images/default-profile.png'; // Default image if no profile picture
            }

            // Show the modal
            document.getElementById('editStudentModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Close Edit Modal
function closeEditModal() {
    document.getElementById('editStudentModal').classList.add('hidden');
}

// Handle form submission
// Handle form submission
document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    console.log("Form submitted"); // Debugging: Check if the form submission is triggered

    const formData = new FormData(this);
    console.log("FormData:", formData); // Debugging: Check the form data being sent

    fetch('update_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log("Response received:", response); // Debugging: Check the response
        return response.json();
    })
    .then(data => {
        console.log("Data:", data); // Debugging: Check the parsed JSON response
        if (data.success) {
            alert("Student updated successfully!");
            location.reload(); // Reload the page to reflect changes
        } else {
            alert("Error updating student: " + data.error);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debugging: Check for fetch errors
        alert("An unexpected error occurred.");
    });
});

// Delete Student Function
function deleteStudent(idno) {
    if (confirm("Are you sure you want to delete this student?")) {
        fetch(`delete_student.php?idno=${idno}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Student deleted successfully!");
                location.reload(); // Reload the page to reflect changes
            } else {
                alert("Error deleting student: " + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An unexpected error occurred.");
        });
    }
}

// Open Add Modal
function openAddModal() {
    document.getElementById('addStudentModal').classList.remove('hidden');
}

// Close Add Modal
function closeAddModal() {
    document.getElementById('addStudentModal').classList.add('hidden');
}

// Handle Add Student Form Submission
document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Get form data
    const formData = new FormData(this);

    // Generate default password: first 4 letters of last name + first 4 digits of ID number
    const lastName = formData.get('lastname').substring(0, 4).toLowerCase();
    const idNo = formData.get('idno').toString().substring(0, 4);
    const defaultPassword = lastName + idNo;

    // Add the default password to the form data
    formData.append('password', defaultPassword);

    // Send the form data to the server
    fetch('add_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Student added successfully!");
            location.reload(); // Reload the page to reflect changes
        } else {
            alert("Error adding student: " + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("An unexpected error occurred.");
    });
});
// Preview image on file select for Add Student Modal
document.getElementById("add-profile-picture-upload").addEventListener("change", function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Update the preview image source
            document.getElementById("add-profile-picture-preview").src = e.target.result;
        };
        reader.readAsDataURL(file); // Read the file as a data URL
    }
});

// Toggle password visibility in the edit modal
document.getElementById("toggleEditPassword").addEventListener("click", function () {
    const passwordInput = document.getElementById("editPassword");
    const icon = this;

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("zmdi-lock");
        icon.classList.add("zmdi-lock-open"); // Change icon to "open lock"
    } else {
        passwordInput.type = "password";
        icon.classList.remove("zmdi-lock-open");
        icon.classList.add("zmdi-lock"); // Change icon back to "lock"
    }
});
// Pagination functionality
let currentPage = 1;
let entriesPerPage = <?php echo count($sitinData); ?>; // Default to showing all entries
let totalPages = 1; // Only one page when showing all entries
const totalEntries = <?php echo count($sitinData); ?>;

// Initialize pagination
function initializePagination() {
    // Set the select element to "All" by default
    document.getElementById('entries').value = 'all';
    updatePaginationUI();
    showPage(currentPage);
}

// Update pagination UI (page numbers, buttons, etc.)
function updatePaginationUI() {
    const pageNumbers = document.getElementById('pageNumbers');
    pageNumbers.innerHTML = '';

    // Update entry counts
    const startEntry = totalEntries === 0 ? 0 : (currentPage - 1) * entriesPerPage + 1;
    const endEntry = totalEntries === 0 ? 0 : Math.min(currentPage * entriesPerPage, totalEntries);
    
    document.getElementById('startEntry').textContent = startEntry;
    document.getElementById('endEntry').textContent = endEntry;
    document.getElementById('totalEntries').textContent = totalEntries;

    // Hide pagination controls when showing all entries or when there are no entries
    const showPagination = entriesPerPage !== totalEntries && totalEntries > 0;
    
    document.getElementById('firstPage').style.display = showPagination ? '' : 'none';
    document.getElementById('prevPage').style.display = showPagination ? '' : 'none';
    document.getElementById('nextPage').style.display = showPagination ? '' : 'none';
    document.getElementById('lastPage').style.display = showPagination ? '' : 'none';
    pageNumbers.style.display = showPagination ? 'flex' : 'none';

    if (!showPagination) return;

    // Always show first page, current page, and last page
    const pagesToShow = new Set([1, currentPage, totalPages]);

    // Also show pages around current page
    for (let i = Math.max(1, currentPage - 1); i <= Math.min(totalPages, currentPage + 1); i++) {
        pagesToShow.add(i);
    }

    // Convert to array and sort
    const sortedPages = Array.from(pagesToShow).sort((a, b) => a - b);

    let prevPage = 0;
    sortedPages.forEach(page => {
        // Add ellipsis if there's a gap
        if (page - prevPage > 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-3 py-1';
            ellipsis.textContent = '...';
            pageNumbers.appendChild(ellipsis);
        }

        // Add page number button
        const pageButton = document.createElement('button');
        pageButton.className = `px-3 py-1 rounded-md border ${currentPage === page ? 'bg-[#002044] text-white border-[#002044]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'}`;
        pageButton.textContent = page;
        pageButton.addEventListener('click', () => {
            currentPage = page;
            showPage(currentPage);
            updatePaginationUI();
        });
        pageNumbers.appendChild(pageButton);

        prevPage = page;
    });

    // Update button states
    document.getElementById('firstPage').disabled = currentPage === 1;
    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages;
    document.getElementById('lastPage').disabled = currentPage === totalPages;
}

// Show a specific page
function showPage(page) {
    const rows = document.querySelectorAll('#sitinTable tbody tr');
    
    if (entriesPerPage === totalEntries) {
        // Show all rows when "All" is selected
        rows.forEach(row => row.style.display = '');
        return;
    }

    const startIndex = (page - 1) * entriesPerPage;
    const endIndex = Math.min(startIndex + entriesPerPage, rows.length);

    rows.forEach((row, index) => {
        if (index >= startIndex && index < endIndex) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Event listeners for pagination buttons
document.getElementById('firstPage').addEventListener('click', () => {
    currentPage = 1;
    showPage(currentPage);
    updatePaginationUI();
});

document.getElementById('prevPage').addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage--;
        showPage(currentPage);
        updatePaginationUI();
    }
});

document.getElementById('nextPage').addEventListener('click', () => {
    if (currentPage < totalPages) {
        currentPage++;
        showPage(currentPage);
        updatePaginationUI();
    }
});

document.getElementById('lastPage').addEventListener('click', () => {
    currentPage = totalPages;
    showPage(currentPage);
    updatePaginationUI();
});

// Update entries per page
document.getElementById('entries').addEventListener('change', function() {
    if (this.value === 'all') {
        entriesPerPage = totalEntries;
        currentPage = 1;
        totalPages = 1;
    } else {
        entriesPerPage = parseInt(this.value);
        currentPage = 1;
        totalPages = Math.ceil(totalEntries / entriesPerPage);
    }
    
    showPage(currentPage);
    updatePaginationUI();
});

// Initialize pagination on page load
initializePagination();
    </script>