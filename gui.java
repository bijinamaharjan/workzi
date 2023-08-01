import tkinter as tk

def calculate_sum(event):
    try:
        num1 = int(entry1.get())
        num2 = int(entry2.get())
        result = num1 + num2
        output.delete(0, tk.END)
        output.insert(tk.END, str(result))
    except ValueError:
        output.delete(0, tk.END)
        output.insert(tk.END, "Invalid input")

def calculate_difference(event):
    try:
        num1 = int(entry1.get())
        num2 = int(entry2.get())
        result = num1 - num2
        output.delete(0, tk.END)
        output.insert(tk.END, str(result))
    except ValueError:
        output.delete(0, tk.END)
        output.insert(tk.END, "Invalid input")

# Create the main window
window = tk.Tk()
window.title("Sum and Difference Calculator")

# Create input fields
label1 = tk.Label(window, text="Number 1:")
label1.pack()
entry1 = tk.Entry(window)
entry1.pack()

label2 = tk.Label(window, text="Number 2:")
label2.pack()
entry2 = tk.Entry(window)
entry2.pack()

# Create the output field
label3 = tk.Label(window, text="Result:")
label3.pack()
output = tk.Entry(window)
output.pack()

# Bind mouse events to the calculate functions
entry1.bind("<Button-1>", calculate_sum)
entry2.bind("<Button-1>", calculate_sum)
entry1.bind("<ButtonRelease-1>", calculate_difference)
entry2.bind("<ButtonRelease-1>", calculate_difference)

# Start the GUI event loop
window.mainloop()
