param(
    [Parameter(Mandatory = $true)]
    [string]$PrinterName,

    [Parameter(Mandatory = $true)]
    [string]$InputFile
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $InputFile -PathType Leaf)) {
    throw 'Input file does not exist.'
}

if ([string]::IsNullOrWhiteSpace($PrinterName)) {
    throw 'Printer name is empty.'
}

if (-not ('Agecs.RawPrinter' -as [type])) {
    Add-Type -TypeDefinition @'
using System;
using System.ComponentModel;
using System.Runtime.InteropServices;

namespace Agecs
{
    public static class RawPrinter
    {
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private class DOCINFO
        {
            [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
            [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
            [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
        }

        [DllImport("winspool.drv", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern bool OpenPrinter(string printerName, out IntPtr printerHandle, IntPtr defaults);

        [DllImport("winspool.drv", SetLastError = true)]
        private static extern bool ClosePrinter(IntPtr printerHandle);

        [DllImport("winspool.drv", SetLastError = true, CharSet = CharSet.Unicode)]
        private static extern int StartDocPrinter(IntPtr printerHandle, int level, [In] DOCINFO documentInfo);

        [DllImport("winspool.drv", SetLastError = true)]
        private static extern bool EndDocPrinter(IntPtr printerHandle);

        [DllImport("winspool.drv", SetLastError = true)]
        private static extern bool StartPagePrinter(IntPtr printerHandle);

        [DllImport("winspool.drv", SetLastError = true)]
        private static extern bool EndPagePrinter(IntPtr printerHandle);

        [DllImport("winspool.drv", SetLastError = true)]
        private static extern bool WritePrinter(IntPtr printerHandle, byte[] bytes, int count, out int written);

        public static void Send(string printerName, byte[] bytes)
        {
            if (bytes == null || bytes.Length == 0)
                throw new ArgumentException("Print payload is empty.", "bytes");

            IntPtr handle;
            if (!OpenPrinter(printerName, out handle, IntPtr.Zero))
                throw new Win32Exception(Marshal.GetLastWin32Error(), "Printer could not be opened.");

            bool documentStarted = false;
            bool pageStarted = false;
            try
            {
                DOCINFO info = new DOCINFO {
                    pDocName = "ECOGEST listare directa",
                    pDataType = "RAW"
                };

                if (StartDocPrinter(handle, 1, info) == 0)
                    throw new Win32Exception(Marshal.GetLastWin32Error(), "Print job could not be created.");
                documentStarted = true;

                if (!StartPagePrinter(handle))
                    throw new Win32Exception(Marshal.GetLastWin32Error(), "Print page could not be started.");
                pageStarted = true;

                int written;
                if (!WritePrinter(handle, bytes, bytes.Length, out written) || written != bytes.Length)
                    throw new Win32Exception(Marshal.GetLastWin32Error(), "Print payload was not accepted completely.");
            }
            finally
            {
                if (pageStarted) EndPagePrinter(handle);
                if (documentStarted) EndDocPrinter(handle);
                ClosePrinter(handle);
            }
        }
    }
}
'@
}

$payload = [System.IO.File]::ReadAllBytes($InputFile)
[Agecs.RawPrinter]::Send($PrinterName, $payload)
[Console]::Out.Write('AGECS_RAW_PRINT_OK')

