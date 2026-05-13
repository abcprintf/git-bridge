package provider

// Branch คือผลลัพธ์จากการสร้าง branch
type Branch struct {
	Name    string
	WebURL  string // URL ของ branch เช่น https://github.com/owner/repo/tree/branch
	RepoURL string // URL ของ repo (ไม่มี branch) สำหรับ deep link เปิด IDE
}

// Provider คือ interface กลางสำหรับ Git hosting ทุกเจ้า
// ปัจจุบัน implement: GitLab, GitHub
type Provider interface {
	// CreateBranch สร้าง branch ชื่อ branchName จาก base ref
	// return (branch, alreadyExists, error)
	CreateBranch(branchName, ref string) (*Branch, bool, error)

	// Name คืนชื่อ provider สำหรับ logging
	Name() string
}
